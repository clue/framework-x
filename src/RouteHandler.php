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
            $handler = new MiddlewareHandler(array_map(
                function ($handler) {
                    return is_callable($handler) ? $handler : $this->container->callable($handler);
                },
                array_merge([$handler], $handlers)
            ));
        } elseif (!is_callable($handler)) {
            $handler = $this->container->callable($handler);
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
}
