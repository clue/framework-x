<?php

namespace FrameworkX;

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

    /** @param array<string,callable():(object|scalar|null) | object | scalar | null>|ContainerInterface $loader */
    public function __construct($loader = [])
    {
        /** @var mixed $loader explicit type check for mixed if user ignores parameter type */
        if (!\is_array($loader) && !$loader instanceof ContainerInterface) {
            throw new \TypeError(
                'Argument #1 ($loader) must be of type array|Psr\Container\ContainerInterface, ' . $this->gettype($loader) . ' given'
            );
        }

        foreach (($loader instanceof ContainerInterface ? [] : $loader) as $name => $value) {
            if (
                (!\is_object($value) && !\is_scalar($value) && $value !== null) ||
                (!$value instanceof $name && !$value instanceof \Closure && !\is_string($value) && \strpos($name, '\\') !== false)
            ) {
                throw new \BadMethodCallException('Map for ' . $name . ' contains unexpected ' . $this->gettype($value));
            }
        }
        $this->container = $loader;

        // prefer reading environment from `$_ENV` and `$_SERVER`, only fall back to `getenv()` in thread-safe environments
        $this->useProcessEnv = \ZEND_THREAD_SAFE === false || \in_array(\PHP_SAPI, ['cli', 'cli-server', 'cgi-fcgi', 'fpm-fcgi'], true);
    }

    /** @return mixed */
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
     * @internal
     */
    public function callable(string $class): callable
    {
        return function (ServerRequestInterface $request, ?callable $next = null) use ($class) {
            // Check `$class` references a valid class name that can be autoloaded
            if (\is_array($this->container) && !\class_exists($class, true) && !interface_exists($class, false) && !trait_exists($class, false)) {
                throw new \BadMethodCallException('Request handler class ' . $class . ' not found');
            }

            try {
                if ($this->container instanceof ContainerInterface) {
                    $handler = $this->container->get($class);
                } else {
                    $handler = $this->loadObject($class);
                }
            } catch (\Throwable $e) {
                throw new \BadMethodCallException(
                    'Request handler class ' . $class . ' failed to load: ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            // Check `$handler` references a class name that is callable, i.e. has an `__invoke()` method.
            // This initial version is intentionally limited to checking the method name only.
            // A follow-up version will likely use reflection to check request handler argument types.
            if (!is_callable($handler)) {
                throw new \BadMethodCallException('Request handler class "' . $class . '" has no public __invoke() method');
            }

            // invoke request handler as middleware handler or final controller
            if ($next === null) {
                return $handler($request);
            }
            return $handler($request, $next);
        };
    }

    /**
     * @param class-string|object $class
     * @param string $method
     * @return callable(ServerRequestInterface,?callable=null)
     * @internal
     */
    public function callableMethod($class, string $method): callable
    {
        return function (ServerRequestInterface $request, ?callable $next = null) use ($class, $method) {
            // Get a controller instance - either use the object directly or instantiate from class name
            if (is_object($class)) {
                $handler = $class;
            } else {
                // Check if class exists and is valid
                if (\is_array($this->container) && !\class_exists($class, true) && !interface_exists($class, false) && !trait_exists($class, false)) {
                    throw new \BadMethodCallException('Request handler class ' . $class . ' not found');
                }

                try {
                    if ($this->container instanceof ContainerInterface) {
                        $handler = $this->container->get($class);
                    } else {
                        $handler = $this->loadObject($class);
                    }
                } catch (\Throwable $e) {
                    throw new \BadMethodCallException(
                        'Request handler class ' . $class . ' failed to load: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }

            // Ensure $handler is an object at this point
            assert(is_object($handler));
            
            // Check if method exists on the controller
            if (!method_exists($handler, $method)) {
                throw new \BadMethodCallException('Request handler class "' . (is_object($class) ? get_class($class) : $class) . '" has no public ' . $method . '() method');
            }

            // invoke controller method as middleware handler or final controller
            if ($next === null) {
                return $handler->$method($request);
            }
            return $handler->$method($request, $next);
        };
    }

    /** @internal */
    public function getEnv(string $name): ?string
    {
        assert(\preg_match('/^[A-Z][A-Z0-9_]+$/', $name) === 1);

        if ($this->container instanceof ContainerInterface && $this->container->has($name)) {
            $value = $this->container->get($name);
        } elseif ($this->hasVariable($name)) {
            $value = $this->loadVariable($name, 'mixed', true, 64);
        } else {
            return null;
        }

        if (!\is_string($value) && $value !== null) {
            throw new \TypeError('Environment variable $' . $name . ' expected type string|null, but got ' . $this->gettype($value));
        }

        return $value;
    }

    /** @internal */
    public function getAccessLogHandler(): AccessLogHandler
    {
        if ($this->container instanceof ContainerInterface) {
            if ($this->container->has(AccessLogHandler::class)) {
                // @phpstan-ignore-next-line method return type will ensure correct type or throw `TypeError`
                return $this->container->get(AccessLogHandler::class);
            } else {
                return new AccessLogHandler();
            }
        }
        return $this->loadObject(AccessLogHandler::class);
    }

    /** @internal */
    public function getErrorHandler(): ErrorHandler
    {
        if ($this->container instanceof ContainerInterface) {
            if ($this->container->has(ErrorHandler::class)) {
                // @phpstan-ignore-next-line method return type will ensure correct type or throw `TypeError`
                return $this->container->get(ErrorHandler::class);
            } else {
                return new ErrorHandler();
            }
        }
        return $this->loadObject(ErrorHandler::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $name
     * @return T
     * @throws \BadMethodCallException if object of type $name can not be loaded
     */
    private function loadObject(string $name, int $depth = 64) /*: object (PHP 7.2+) */
    {
        assert(\is_array($this->container));

        if (\array_key_exists($name, $this->container)) {
            if (\is_string($this->container[$name])) {
                if ($depth < 1) {
                    throw new \BadMethodCallException('Factory for ' . $name . ' is recursive');
                }

                // @phpstan-ignore-next-line because type of container value is explicitly checked after getting here
                $value = $this->loadObject($this->container[$name], $depth - 1);
                if (!$value instanceof $name) {
                    throw new \BadMethodCallException('Factory for ' . $name . ' returned unexpected ' . $this->gettype($value));
                }

                $this->container[$name] = $value;
            } elseif ($this->container[$name] instanceof \Closure) {
                // build list of factory parameters based on parameter types
                $closure = new \ReflectionFunction($this->container[$name]);
                $params = $this->loadFunctionParams($closure, $depth, true);

                // invoke factory with list of parameters
                $value = $params === [] ? ($this->container[$name])() : ($this->container[$name])(...$params);

                if (\is_string($value)) {
                    if ($depth < 1) {
                        throw new \BadMethodCallException('Factory for ' . $name . ' is recursive');
                    }

                    // @phpstan-ignore-next-line because type of container value is explicitly checked after getting here
                    $value = $this->loadObject($value, $depth - 1);
                }
                if (!$value instanceof $name) {
                    throw new \BadMethodCallException('Factory for ' . $name . ' returned unexpected ' . $this->gettype($value));
                }

                $this->container[$name] = $value;
            } elseif (!$this->container[$name] instanceof $name) {
                throw new \BadMethodCallException('Map for ' . $name . ' contains unexpected ' . $this->gettype($this->container[$name]));
            }

            assert($this->container[$name] instanceof $name);

            return $this->container[$name];
        }

        // Check `$name` references a valid class name that can be autoloaded
        if (!\class_exists($name, true) && !interface_exists($name, false) && !trait_exists($name, false)) {
            throw new \BadMethodCallException('Class ' . $name . ' not found');
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
            throw new \BadMethodCallException('Cannot instantiate ' . $modifier . ' '. $name);
        }

        // build list of constructor parameters based on parameter types
        $ctor = $class->getConstructor();
        $params = $ctor === null ? [] : $this->loadFunctionParams($ctor, $depth, false);

        // instantiate with list of parameters
        // @phpstan-ignore-next-line because `$class->newInstance()` is known to return `T`
        return $this->container[$name] = $params === [] ? new $name() : $class->newInstance(...$params);
    }

    /**
     * @return list<mixed>
     * @throws \BadMethodCallException if either parameter can not be loaded
     */
    private function loadFunctionParams(\ReflectionFunctionAbstract $function, int $depth, bool $allowVariables): array
    {
        $params = [];
        foreach ($function->getParameters() as $parameter) {
            $params[] = $this->loadParameter($parameter, $depth, $allowVariables);
        }

        return $params;
    }

    /**
     * @return mixed
     * @throws \BadMethodCallException if $parameter can not be loaded
     */
    private function loadParameter(\ReflectionParameter $parameter, int $depth, bool $allowVariables) /*: mixed (PHP 8.0+) */
    {
        assert(\is_array($this->container));

        $type = $parameter->getType();
        $hasDefault = $parameter->isDefaultValueAvailable() || ((!$type instanceof \ReflectionNamedType || $type->getName() !== 'mixed') && $parameter->allowsNull());

        // abort for union types (PHP 8.0+) and intersection types (PHP 8.1+)
        // @phpstan-ignore-next-line for PHP < 8
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) { // @codeCoverageIgnoreStart
            if ($hasDefault) {
                return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            }
            throw new \BadMethodCallException(self::parameterError($parameter) . ' expects unsupported type ' . $type);
        } // @codeCoverageIgnoreEnd

        // load container variables if parameter name is known
        assert($type === null || $type instanceof \ReflectionNamedType);
        if ($allowVariables && $this->hasVariable($parameter->getName())) {
            return $this->loadVariable($parameter->getName(), $type === null ? 'mixed' : $type->getName(), $parameter->allowsNull(), $depth);
        }

        // abort if parameter is untyped and not explicitly defined by container variable
        if ($type === null) {
            assert($parameter->allowsNull());
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new \BadMethodCallException(self::parameterError($parameter) . ' has no type');
        }

        // use default/nullable argument if not loadable as container variable or by type
        assert($type instanceof \ReflectionNamedType);
        if ($hasDefault && ($type->isBuiltin() || !\array_key_exists($type->getName(), $this->container))) {
            return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }

        // abort if required container variable is not defined or for any other primitive types (array etc.)
        if ($type->isBuiltin()) {
            if ($allowVariables) {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' is not defined');
            } else {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' expects unsupported type ' . $type->getName());
            }
        }

        // abort for unreasonably deep nesting or recursive types
        if ($depth < 1) {
            throw new \BadMethodCallException(self::parameterError($parameter) . ' is recursive');
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
     * @throws \BadMethodCallException if $name is not a valid container variable
     */
    private function loadVariable(string $name, string $type, bool $nullable, int $depth) /*: object|string|int|float|bool|null (PHP 8.0+) */
    {
        assert($this->hasVariable($name));
        assert(\is_array($this->container) || !$this->container->has($name));

        if (\is_array($this->container) && ($this->container[$name] ?? null) instanceof \Closure) {
            if ($depth < 1) {
                throw new \BadMethodCallException('Container variable $' . $name . ' is recursive');
            }

            // build list of factory parameters based on parameter types
            $factory = $this->container[$name];
            assert($factory instanceof \Closure);
            $closure = new \ReflectionFunction($factory);
            $params = $this->loadFunctionParams($closure, $depth - 1, true);

            // invoke factory with list of parameters
            $value = $params === [] ? $factory() : $factory(...$params);

            if (!\is_object($value) && !\is_scalar($value) && $value !== null) {
                throw new \BadMethodCallException('Container variable $' . $name . ' expected type object|scalar|null from factory, but got ' . $this->gettype($value));
            }

            $this->container[$name] = $value;
        } elseif (\is_array($this->container) && \array_key_exists($name, $this->container)) {
            $value = $this->container[$name];
        } elseif (isset($_ENV[$name])) {
            assert(\is_string($_ENV[$name]));
            $value = $_ENV[$name];
        } elseif (isset($_SERVER[$name])) {
            assert(\is_string($_SERVER[$name]));
            $value = $_SERVER[$name];
        } else {
            $value = \getenv($name);
            assert($this->useProcessEnv && $value !== false);
        }

        assert(\is_object($value) || \is_scalar($value) || $value === null);

        // allow null values if parameter is marked nullable or untyped or mixed
        if ($nullable && $value === null) {
            return null;
        }

        // skip type checks and allow all values if expected type is undefined or mixed (PHP 8+)
        if ($type === 'mixed') {
            return $value;
        }

        if (
            (\is_object($value) && !$value instanceof $type) ||
            (!\is_object($value) && !\in_array($type, ['string', 'int', 'float', 'bool'])) ||
            ($type === 'string' && !\is_string($value)) || ($type === 'int' && !\is_int($value)) || ($type === 'float' && !\is_float($value)) || ($type === 'bool' && !\is_bool($value))
        ) {
            throw new \BadMethodCallException('Container variable $' . $name . ' expected type ' . $type . ', but got ' . $this->gettype($value));
        }

        return $value;
    }

    /** @throws void */
    private static function parameterError(\ReflectionParameter $parameter): string
    {
        $function = $parameter->getDeclaringFunction();
        $name = $function->getShortName();
        if ($name[0] === '{') { // $function->isAnonymous() (PHP 8.2+)
            // use PHP 8.4+ format including closure file and line on all PHP versions: https://3v4l.org/tAs7s
            $name = '{closure:' . $function->getFileName() . ':' . $function->getStartLine() . '}';
        } elseif (($class = $parameter->getDeclaringClass()) !== null) {
            $name = explode("\0", $class->getName())[0] . '::' . $name;
        }

        return 'Argument #' . ($parameter->getPosition() + 1) . ' ($' . $parameter->getName() . ') of ' . $name . '()';
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
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }
}
