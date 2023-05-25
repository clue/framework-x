<?php

namespace FrameworkX\Io;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use FrameworkX\AccessLogHandler;
use FrameworkX\Container;
use FrameworkX\ErrorHandler;
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

    /** @var Container */
    private $container;

    public function __construct(Container $container = null)
    {
        $this->routeCollector = new RouteCollector(new RouteParser(), new RouteGenerator());
        $this->errorHandler = new ErrorHandler();
        $this->container = $container ?? new Container();
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
            \array_unshift($handlers, $handler);
            \end($handlers);
        } else {
            $handlers = [$handler];
        }

        $last = key($handlers);
        $container = $this->container;
        foreach ($handlers as $i => $handler) {
            if ($handler instanceof Container && $i !== $last) {
                $container = $handler;
                unset($handlers[$i]);
            } elseif ($handler instanceof AccessLogHandler || $handler === AccessLogHandler::class) {
                throw new \TypeError('AccessLogHandler may currently only be passed as a global middleware');
            } elseif (!\is_callable($handler)) {
                $handlers[$i] = $container->callable($handler);
            }
        }

        /** @var non-empty-array<callable> $handlers */
        $handler = \count($handlers) > 1 ? new MiddlewareHandler(array_values($handlers)) : \reset($handlers);
        $this->routeDispatcher = null;
        $this->routeCollector->addRoute($methods, $route, $handler);
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $target = $request->getRequestTarget();
        if ($target[0] !== '/' && $target !== '*') {
            return $this->errorHandler->requestProxyUnsupported();
        } elseif ($target !== '*') {
            $target = $request->getUri()->getPath();
        }

        if ($this->routeDispatcher === null) {
            $this->routeDispatcher = new RouteDispatcher($this->routeCollector->getData());
        }

        $routeInfo = $this->routeDispatcher->dispatch($request->getMethod(), $target);
        assert(\is_array($routeInfo) && isset($routeInfo[0]));

        // happy path: matching route found, assign route attributes and invoke request handler
        if ($routeInfo[0] === \FastRoute\Dispatcher::FOUND) {
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            foreach ($vars as $key => $value) {
                $request = $request->withAttribute($key, rawurldecode($value));
            }

            return $handler($request);
        }

        // no matching route found: report error `404 Not Found`
        if ($routeInfo[0] === \FastRoute\Dispatcher::NOT_FOUND) {
            return $this->errorHandler->requestNotFound();
        }

        // unexpected request method for route: report error `405 Method Not Allowed`
        assert($routeInfo[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED);
        assert(\is_array($routeInfo[1]) && \count($routeInfo[1]) > 0);

        return $this->errorHandler->requestMethodNotAllowed($routeInfo[1]);
    }
}
