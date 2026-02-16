<?php

namespace FrameworkX;

use FrameworkX\Io\LogStreamHandler;
use FrameworkX\Io\RouteHandler;
use FrameworkX\Runner\HttpServerRunner;
use FrameworkX\Runner\SapiRunner;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @final
 */
class Container
{
    /** @var array<string,object|callable():(object|scalar|null)|scalar|null>|ContainerInterface */
    private $container;

    /** @var bool */
    private $useProcessEnv;

    /**
     * @param array<string,callable():(object|scalar|null) | object | scalar | null>|ContainerInterface $config
     * @throws \TypeError if given $config is invalid
     */
    public function __construct($config = [])
    {
        /** @var mixed $config explicit type check for mixed if user ignores parameter type */
        if (!\is_array($config) && !$config instanceof ContainerInterface) {
            throw new \TypeError(
                'Argument #1 ($config) must be of type array|Psr\Container\ContainerInterface, ' . $this->gettype($config) . ' given'
            );
        }

        foreach (($config instanceof ContainerInterface ? [] : $config) as $name => $value) {
            if (!$value instanceof $name && !$value instanceof \Closure && !\is_string($value) && \strpos($name, '\\') !== false) {
                throw new \TypeError(
                    'Argument #1 ($config) for key "' . $name . '" must be of type ' . $name . '|Closure|string, ' . $this->gettype($value) . ' given'
                );
            }
            if (!\is_object($value) && !\is_scalar($value) && $value !== null) {
                throw new \TypeError(
                    'Argument #1 ($config) for key "' . $name . '" must be of type object|string|int|float|bool|null|Closure, ' . $this->gettype($value) . ' given'
                );
            }
        }
        $this->container = $config;

        // prefer reading environment from `$_ENV` and `$_SERVER`, only fall back to `getenv()` in thread-safe environments
        $this->useProcessEnv = \ZEND_THREAD_SAFE === false || \in_array(\PHP_SAPI, ['cli', 'cli-server', 'cgi-fcgi', 'fpm-fcgi'], true);
    }

    /**
     * @return mixed returns whatever the $next handler returns
     * @throws \BadMethodCallException if used as a final request handler
     * @throws \Throwable if $next handler throws unexpected exception
     */
    public function __invoke(ServerRequestInterface $request, ?callable $next = null)
    {
        if ($next === null) {
            // You don't want to end up here. This only happens if you use the
            // container as a final request handler instead of as a middleware.
            // In this case, you should omit the container or add another final
            // request handler behind the container in the middleware chain.
            throw new \BadMethodCallException('Container should not be used as final request handler');
        }

        // If the container is used as a middleware, simply forward to the next
        // request handler. As an additional optimization, the container would
        // usually be filtered out from a middleware chain as this is a NO-OP.
        return $next($request);
    }

    /**
     * @param class-string $class
     * @return callable(ServerRequestInterface,?callable=null)
     * @throws void
     * @internal
     */
    public function callable(string $class): callable
    {
        // may be any class name except AccessLogHandler or Container itself
        \assert(!\in_array($class, [AccessLogHandler::class, self::class], true));

        return function (ServerRequestInterface $request, ?callable $next = null) use ($class) {
            try {
                if ($this->container instanceof ContainerInterface) {
                    $handler = $this->container->get($class);
                } else {
                    $handler = $this->loadObject($class);
                }
            } catch (\Throwable $e) {
                throw new \Error(
                    'Request handler class ' . $class . ' failed to load: ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            // Check `$handler` references a class name that is callable, i.e. has an `__invoke()` method.
            // This initial version is intentionally limited to checking the method name only.
            // A follow-up version will likely use reflection to check request handler argument types.
            if (!\is_callable($handler)) {
                throw new \Error(
                    'Request handler ' . \explode("\0", $class)[0] . ' has no public __invoke() method'
                );
            }

            // invoke request handler as middleware handler or final controller
            if ($next === null) {
                return $handler($request);
            }
            return $handler($request, $next);
        };
    }

    /**
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Throwable if container factory function throws unexpected exception
     * @internal
     */
    public function getEnv(string $name): ?string
    {
        \assert(\preg_match('/^[A-Z][A-Z0-9_]+$/', $name) === 1);

        if ($this->container instanceof ContainerInterface && $this->container->has($name)) {
            $value = $this->container->get($name);
        } elseif ($this->hasVariable($name)) {
            $value = $this->loadVariable($name);
        } else {
            return null;
        }

        if (!\is_string($value) && $value !== null) {
            throw new \TypeError(
                'Return value of ' . __METHOD__ . '() for $' . $name . ' must be of type string|null, ' . $this->gettype($value) . ' returned'
            );
        }

        return $value;
    }

    /**
     * [Internal] Get an object of given class from container
     *
     * @template T of object
     * @param class-string<T> $class
     * @return object returns an instance of given $class or throws if it can not be instantiated
     * @phpstan-return T
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Error if object of type $class can not be loaded
     * @throws \Throwable if container factory function throws unexpected exception
     * @internal
     */
    public function getObject(string $class) /*: object (PHP 7.2+) */
    {
        if ($this->container instanceof ContainerInterface && $this->container->has($class)) {
            $value = $this->container->get($class);
            if (!$value instanceof $class) {
                throw new \TypeError(
                    'Return value of ' . \explode("\0", \get_class($this->container))[0] . '::get() for ' . $class . ' must be of type ' . $class . ', ' . $this->gettype($value) . ' returned'
                );
            }
            return $value;
        } elseif ($this->container instanceof ContainerInterface) {
            // fallback for missing required internal classes from PSR-11 adapter
            if ($class === Container::class) {
                return $this; // @phpstan-ignore-line returns instanceof `T`
            } elseif ($class === RouteHandler::class) {
                return new RouteHandler($this); // @phpstan-ignore-line returns instanceof `T`
            } elseif ($class === HttpServerRunner::class) {
                return new HttpServerRunner(new LogStreamHandler('php://output'), $this->getEnv('X_LISTEN')); // @phpstan-ignore-line returns instanceof `T`
            }
            return new $class();
        }

        return $this->loadObject($class);
    }

    /**
     * [Internal] Get the app runner appropriate for this environment from container
     *
     * By default, this method returns an instance of `HttpServerRunner` when
     * running in CLI mode, and an instance of `SapiRunner` when running in a
     * traditional web server environment.
     *
     * For more advanced use cases, this behavior can be overridden by setting
     * the `X_EXPERIMENTAL_RUNNER` environment variable to the desired runner
     * class name. The specified class must be invokable with the main request
     * handler signature. Note that this is an experimental feature and the API
     * may be subject to change in future releases.
     *
     * @return HttpServerRunner|SapiRunner|callable(callable(ServerRequestInterface):(\Psr\Http\Message\ResponseInterface|\React\Promise\PromiseInterface<\Psr\Http\Message\ResponseInterface>)):void
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Throwable if container factory function throws unexpected exception
     * @internal
     * @see App::run()
     */
    public function getRunner(): callable /*: HttpServerRunner|SapiRunner|callable (PHP 8.0+) */
    {
        // @phpstan-ignore-next-line `getObject()` already performs type checks if `getEnv()` returns an invalid class
        $runner = $this->getObject($this->getEnv('X_EXPERIMENTAL_RUNNER') ?? (\PHP_SAPI === 'cli' ? HttpServerRunner::class : SapiRunner::class));
        if (!\is_callable($runner)) {
            throw new \TypeError(
                'Return value of ' . __METHOD__ . '() must be of type callable, ' . $this->gettype($runner) . ' returned'
            );
        }
        return $runner;
    }

    /**
     * @template T of object
     * @param class-string<T> $name
     * @return object returns an instance of given class $name or throws if it can not be instantiated
     * @phpstan-return T
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Error if object of type $name can not be loaded
     * @throws \Throwable if container factory function throws unexpected exception
     */
    private function loadObject(string $name, int $depth = 64) /*: object (PHP 7.2+) */
    {
        \assert(\is_array($this->container));

        if ($name === HttpServerRunner::class && !\array_key_exists(HttpServerRunner::class, $this->container)) {
            // special case: create HttpServerRunner with X_LISTEN environment variable
            $this->container[HttpServerRunner::class] = static function (?string $X_LISTEN = null): HttpServerRunner {
                return new HttpServerRunner(new LogStreamHandler('php://output'), $X_LISTEN);
            };
        }

        if (\array_key_exists($name, $this->container)) {
            if (\is_string($this->container[$name])) {
                if ($depth < 1) {
                    throw new \Error('Container config for ' . $name . ' is recursive');
                }

                // @phpstan-ignore-next-line because type of container value is explicitly checked after getting here
                $value = $this->loadObject($this->container[$name], $depth - 1);
                if (!$value instanceof $name) {
                    throw new \TypeError(
                        'Return value of ' . __METHOD__ . '() for ' . $name . ' must be of type ' . $name . ', ' . $this->gettype($value) . ' returned'
                    );
                }

                $this->container[$name] = $value;
            } elseif ($this->container[$name] instanceof \Closure) {
                $factory = $this->container[$name];
                $closure = new \ReflectionFunction($factory);

                // invoke factory with list of parameters
                // temporarily unset factory reference to allow loading recursive variables from environment
                try {
                    unset($this->container[$name]);
                    $value = $factory(...$this->loadFunctionParams($closure, $depth, true, \explode("\0", $name)[0]));
                } finally {
                    $this->container[$name] = $factory;
                }

                if (\is_string($value)) {
                    if ($depth < 1) {
                        throw new \Error('Container config for ' . $name . ' is recursive');
                    }

                    // @phpstan-ignore-next-line because type of container value is explicitly checked after getting here
                    $value = $this->loadObject($value, $depth - 1);
                }
                if (!$value instanceof $name) {
                    throw new \TypeError(
                        'Return value of ' . self::functionName($closure) . ' for ' . $name . ' must be of type ' . $name . ', ' . $this->gettype($value) . ' returned'
                    );
                }

                $this->container[$name] = $value;
            } elseif (!$this->container[$name] instanceof $name) {
                throw new \TypeError(
                    'Return value of ' . __METHOD__ . '() for ' . $name . ' must be of type ' . $name . ', ' . $this->gettype($this->container[$name]) . ' returned'
                );
            }

            \assert($this->container[$name] instanceof $name);

            return $this->container[$name];
        } elseif ($name === self::class) {
            // return container itself for self-references unless explicitly configured (see above)
            return $this; // @phpstan-ignore-line returns instanceof `T`
        }

        // Check `$name` references a valid class name that can be autoloaded
        if (!\class_exists($name, true) && !\interface_exists($name, false) && !\trait_exists($name, false)) {
            throw new \Error('Class ' . $name . ' not found');
        }

        $class = new \ReflectionClass($name);
        if (!$class->isInstantiable()) {
            $modifier = 'class';
            if ($class->isInterface()) {
                $modifier = 'interface';
            } elseif ($class->isAbstract()) {
                $modifier = 'abstract class';
            } elseif ($class->isTrait()) {
                $modifier = 'trait';
            }
            throw new \Error('Cannot instantiate ' . $modifier . ' '. $name);
        }

        // build list of constructor parameters based on parameter types
        $ctor = $class->getConstructor();
        $params = $ctor === null ? [] : $this->loadFunctionParams($ctor, $depth, false, '');

        // instantiate with list of parameters
        // @phpstan-ignore-next-line because `$class->newInstance()` is known to return `T`
        return $this->container[$name] = $params === [] ? new $name() : $class->newInstance(...$params);
    }

    /**
     * @return list<mixed>
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Error if either parameter can not be loaded
     * @throws \Throwable if container factory function throws unexpected exception
     */
    private function loadFunctionParams(\ReflectionFunctionAbstract $function, int $depth, bool $allowVariables, string $for): array
    {
        $params = [];
        foreach ($function->getParameters() as $parameter) {
            $params[] = $this->loadParameter($parameter, $depth, $allowVariables, $for);
        }

        return $params;
    }

    /**
     * @return mixed
     * @throws \TypeError if container config or factory returns an unexpected type
     * @throws \Error if $parameter can not be loaded
     * @throws \Throwable if container factory function throws unexpected exception
     */
    private function loadParameter(\ReflectionParameter $parameter, int $depth, bool $allowVariables, string $for) /*: mixed (PHP 8.0+) */
    {
        // abort for unreasonably deep nesting or recursive types
        if ($depth < 1) {
            throw new \Error(self::parameterError($parameter, $for) . ' is recursive');
        }

        \assert(\is_array($this->container));
        $type = $parameter->getType();

        // load container variables if parameter name is known
        if ($allowVariables && $this->hasVariable($parameter->getName())) {
            $value = $this->loadVariable($parameter->getName(), $depth);

            // skip type checks and allow all values if expected type is undefined or mixed (PHP 8+)
            // allow null values if parameter is marked nullable or untyped or mixed
            if ($type === null || ($value === null && $parameter->allowsNull()) || ($type instanceof \ReflectionNamedType && $type->getName() === 'mixed') || $this->validateType($value, $type)) {
                return $value;
            }

            throw new \TypeError(
                self::parameterError($parameter, $for) . ' must be of type ' . self::typeName($type) . ', ' . $this->gettype($value) . ' given'
            );
        }

        // use default argument if not loadable as container variable or by type
        if (
            $parameter->isDefaultValueAvailable() &&
            (!$type instanceof \ReflectionNamedType || $type->isBuiltin() || !\array_key_exists($type->getName(), $this->container))
        ) {
            return $parameter->getDefaultValue();
        }

        // abort if required container variable is not defined or for any other primitive types (array etc.)
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            throw new \Error(
                self::parameterError($parameter, $for) . ' requires container config' . ($type !== null ? ' with type ' . self::typeName($type) : '') . ', none given'
            );
        }

        // @phpstan-ignore-next-line because `$type->getName()` is a `class-string` by definition
        return $this->loadObject($type->getName(), $depth - 1);
    }

    private function hasVariable(string $name): bool
    {
        return (\is_array($this->container) && \array_key_exists($name, $this->container)) || (isset($_ENV[$name]) || (\is_string($_SERVER[$name] ?? null) || ($this->useProcessEnv && \getenv($name) !== false)) && \preg_match('/^[A-Z][A-Z0-9_]+$/', $name));
    }

    /**
     * @return object|string|int|float|bool|null
     * @throws \TypeError if container factory returns an unexpected type
     * @throws \Error if $name can not be loaded
     * @throws \Throwable if container factory function throws unexpected exception
     */
    private function loadVariable(string $name, int $depth = 64) /*: object|string|int|float|bool|null (PHP 8.0+) */
    {
        \assert($this->hasVariable($name));
        \assert(\is_array($this->container) || !$this->container->has($name));

        if (\is_array($this->container) && ($this->container[$name] ?? null) instanceof \Closure) {
            $factory = $this->container[$name];
            \assert($factory instanceof \Closure);
            $closure = new \ReflectionFunction($factory);

            // invoke factory with list of parameters
            // temporarily unset factory reference to allow loading recursive variables from environment
            try {
                unset($this->container[$name]);
                $value = $factory(...$this->loadFunctionParams($closure, $depth - 1, true, '$' . $name));
            } finally {
                $this->container[$name] = $factory;
            }

            if (!\is_object($value) && !\is_scalar($value) && $value !== null) {
                throw new \TypeError(
                    'Return value of ' . self::functionName($closure) . ' for $' . $name . ' must be of type object|string|int|float|bool|null, ' . $this->gettype($value) . ' returned'
                );
            }

            $this->container[$name] = $value;
        } elseif (\is_array($this->container) && \array_key_exists($name, $this->container)) {
            $value = $this->container[$name];
        } elseif (isset($_ENV[$name])) {
            \assert(\is_string($_ENV[$name]));
            $value = $_ENV[$name];
        } elseif (isset($_SERVER[$name])) {
            \assert(\is_string($_SERVER[$name]));
            $value = $_SERVER[$name];
        } else {
            $value = \getenv($name);
            \assert($this->useProcessEnv && $value !== false);
        }

        \assert(\is_object($value) || \is_scalar($value) || $value === null);
        return $value;
    }

    /**
     * @param object|string|int|float|bool|null $value
     * @param \ReflectionType $type
     * @throws void
     */
    private function validateType($value, \ReflectionType $type): bool
    {
        // check union types (PHP 8.0+) and intersection types (PHP 8.1+) and DNF types (PHP 8.2+)
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) { // @codeCoverageIgnoreStart
            $early = $type instanceof \ReflectionUnionType;
            foreach ($type->getTypes() as $type) {
                // return early success if any union type matches
                // return early failure if any intersection type doesn't match
                if ($this->validateType($value, $type) === $early) {
                    return $early;
                }
            }
            return !$early;
        } // @codeCoverageIgnoreEnd

        // if we reach here, we handle only a single named type
        \assert($type instanceof \ReflectionNamedType);
        $type = $type->getName();

        // nullable types and mixed already handled before entering this check
        \assert($type !== 'null' && $type !== 'mixed');

        return (
            (\is_object($value) && $value instanceof $type) ||
            (\is_string($value) && $type === 'string') ||
            (\is_int($value) && $type === 'int') ||
            (\is_float($value) && $type === 'float') ||
            (\is_bool($value) && $type === 'bool')
        );
    }

    /** @throws void */
    private static function functionName(\ReflectionFunctionAbstract $function): string
    {
        $name = $function->getShortName();
        if ($name[0] === '{') { // $function->isAnonymous() (PHP 8.2+)
            // use PHP 8.4+ format including closure file and line on all PHP versions: https://3v4l.org/tAs7s
            $name = '{closure:' . $function->getFileName() . ':' . $function->getStartLine() . '}';
        } elseif ($function instanceof \ReflectionMethod && ($class = $function->getDeclaringClass()) !== null) {
            $name = \explode("\0", $class->getName())[0] . '::' . $name;
        }
        return $name . '()';
    }

    /** @throws void */
    private static function parameterError(\ReflectionParameter $parameter, string $for): string
    {
        return 'Argument #' . ($parameter->getPosition() + 1) . ' ($' . $parameter->getName() . ') of ' . self::functionName($parameter->getDeclaringFunction()) . ($for !== '' ? ' for ' . $for : '');
    }

    /**
     * @param \ReflectionType $type
     * @return string
     * @throws void
     * @see https://www.php.net/manual/en/reflectiontype.tostring.php (PHP 8+)
     */
    private static function typeName(\ReflectionType $type): string
    {
        return $type instanceof \ReflectionNamedType ? ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName() : (string) $type;
    }

    /**
     * @param mixed $value
     * @return string
     * @throws void
     * @see https://www.php.net/manual/en/function.get-debug-type.php (PHP 8+)
     */
    private function gettype($value): string
    {
        if (\is_int($value)) {
            return 'int';
        } elseif (\is_float($value)) {
            return 'float';
        } elseif (\is_bool($value)) {
            return \var_export($value, true);
        } elseif ($value === null) {
            return 'null';
        }
        return \is_object($value) ? \explode("\0", \get_class($value))[0] : \gettype($value);
    }
}
