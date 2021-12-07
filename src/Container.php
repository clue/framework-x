<?php

namespace FrameworkX;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @final
 */
class Container
{
    /** @var array<class-string,object|callable():object> */
    private $container;

    /** @var array<class-string,callable():object | object> */
    public function __construct(array $map = [])
    {
        $this->container = $map;
    }

    public function __invoke(ServerRequestInterface $request, callable $next = null)
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
        return function (ServerRequestInterface $request, callable $next = null) use ($class) {
            // Check `$class` references a valid class name that can be autoloaded
            if (!\class_exists($class, true) && !interface_exists($class, false) && !trait_exists($class, false)) {
                throw new \BadMethodCallException('Request handler class ' . $class . ' not found');
            }

            try {
                $handler = $this->load($class);
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
     * @param class-string $name
     * @return object
     * @throws \BadMethodCallException
     */
    private function load(string $name, int $depth = 64)
    {
        if (isset($this->container[$name])) {
            if ($this->container[$name] instanceof \Closure) {
                $this->container[$name] = ($this->container[$name])();
            }

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
        $params = [];
        $ctor = $class->getConstructor();
        assert($ctor === null || $ctor instanceof \ReflectionMethod);
        foreach ($ctor !== null ? $ctor->getParameters() : [] as $parameter) {
            assert($parameter instanceof \ReflectionParameter);

            // stop building parameters when encountering first optional parameter
            if ($parameter->isOptional()) {
                break;
            }

            // ensure parameter is typed
            $type = $parameter->getType();
            if ($type === null) {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' has no type');
            }

            // if allowed, use null value without injecting any instances
            assert($type instanceof \ReflectionType);
            if ($type->allowsNull()) {
                $params[] = null;
                continue;
            }

            // abort for union types (PHP 8.0+) and intersection types (PHP 8.1+)
            if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' expects unsupported type ' . $type); // @codeCoverageIgnore
            }

            assert($type instanceof \ReflectionNamedType);
            if ($type->isBuiltin()) {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' expects unsupported type ' . $type->getName());
            }

            // abort for unreasonably deep nesting or recursive types
            if ($depth < 1) {
                throw new \BadMethodCallException(self::parameterError($parameter) . ' is recursive');
            }

            $params[] = $this->load($type->getName(), --$depth);
        }

        // instantiate with list of parameters
        return $this->container[$name] = $params === [] ? new $name() : $class->newInstance(...$params);
    }

    private static function parameterError(\ReflectionParameter $parameter): string
    {
        return 'Argument ' . ($parameter->getPosition() + 1) . ' ($' . $parameter->getName() . ') of ' . explode("\0", $parameter->getDeclaringClass()->getName())[0] . '::' . $parameter->getDeclaringFunction()->getName() . '()';
    }
}
