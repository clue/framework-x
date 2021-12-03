<?php

namespace FrameworkX;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

/**
 * @internal
 */
class RouteHandler
{
    /** @var RouteCollector */
    private $routeCollector;

    /** @var ?RouteDispatcher */
    private $routeDispatcher;

    /** @var ErrorHandler */
    private $errorHandler;

    /** @var array<string,mixed> */
    private static $container = [];

    public function __construct()
    {
        $this->routeCollector = new RouteCollector(new RouteParser(), new RouteGenerator());
        $this->errorHandler = new ErrorHandler();
    }

    /**
     * @param string[] $methods
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function map(array $methods, string $route, $handler, ...$handlers): void
    {
        if ($handlers) {
            $handler = new MiddlewareHandler(array_map(
                function ($handler) {
                    return is_callable($handler) ? $handler : self::callable($handler);
                },
                array_merge([$handler], $handlers)
            ));
        } elseif (!is_callable($handler)) {
            $handler = self::callable($handler);
        }

        $this->routeDispatcher = null;
        $this->routeCollector->addRoute($methods, $route, $handler);
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request)
    {
        if ($request->getRequestTarget()[0] !== '/' && $request->getRequestTarget() !== '*') {
            return $this->errorHandler->requestProxyUnsupported($request);
        }

        if ($this->routeDispatcher === null) {
            $this->routeDispatcher = new RouteDispatcher($this->routeCollector->getData());
        }

        $routeInfo = $this->routeDispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return $this->errorHandler->requestNotFound($request);
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                return $this->errorHandler->requestMethodNotAllowed($routeInfo[1]);
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                foreach ($vars as $key => $value) {
                    $request = $request->withAttribute($key, rawurldecode($value));
                }

                return $handler($request);
        }
    } // @codeCoverageIgnore

    /**
     * @param class-string $class
     * @return callable
     */
    private static function callable($class): callable
    {
        return function (ServerRequestInterface $request, callable $next = null) use ($class) {
            // Check `$class` references a valid class name that can be autoloaded
            if (!\class_exists($class, true) && !interface_exists($class, false) && !trait_exists($class, false)) {
                throw new \BadMethodCallException('Request handler class ' . $class . ' not found');
            }

            try {
                $handler = self::load($class);
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

    private static function load(string $name, int $depth = 64)
    {
        if (isset(self::$container[$name])) {
            return self::$container[$name];
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

            $params[] = self::load($type->getName(), --$depth);
        }

        // instantiate with list of parameters
        return self::$container[$name] = $params === [] ? new $name() : $class->newInstance(...$params);
    }

    private static function parameterError(\ReflectionParameter $parameter): string
    {
        return 'Argument ' . ($parameter->getPosition() + 1) . ' ($' . $parameter->getName() . ') of ' . explode("\0", $parameter->getDeclaringClass()->getName())[0] . '::' . $parameter->getDeclaringFunction()->getName() . '()';
    }
}
