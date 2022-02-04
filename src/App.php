<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;

class App
{
    /** @var MiddlewareHandler */
    private $handler;

    /** @var RouteHandler */
    private $router;

    /** @var SapiHandler */
    private $sapi;

    /**
     * Instantiate new X application
     *
     * ```php
     * // instantiate
     * $app = new App();
     *
     * // instantiate with global middleware
     * $app = new App($middleware);
     * $app = new App($middleware1, $middleware2);
     * ```
     *
     * @param callable|class-string ...$middleware
     */
    public function __construct(...$middleware)
    {
        $errorHandler = new ErrorHandler();

        $container = new Container();
        if ($middleware) {
            foreach ($middleware as $i => $handler) {
                if ($handler instanceof Container) {
                    $container = $handler;
                    unset($middleware[$i]);
                } elseif (!\is_callable($handler)) {
                    $middleware[$i] = $container->callable($handler);
                }
            }
        }

        // new MiddlewareHandler([$accessLogHandler, $errorHandler, ...$middleware, $routeHandler])
        \array_unshift($middleware, $errorHandler);

        // only log for built-in webserver and PHP development webserver by default, others have their own access log
        if (\PHP_SAPI === 'cli' || \PHP_SAPI === 'cli-server') {
            \array_unshift($middleware, new AccessLogHandler());
        }

        $this->router = new RouteHandler($container);
        $middleware[] = $this->router;
        $this->handler = new MiddlewareHandler($middleware);
        $this->sapi = new SapiHandler();
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function get(string $route, $handler, ...$handlers): void
    {
        $this->map(['GET'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function head(string $route, $handler, ...$handlers): void
    {
        $this->map(['HEAD'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function post(string $route, $handler, ...$handlers): void
    {
        $this->map(['POST'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function put(string $route, $handler, ...$handlers): void
    {
        $this->map(['PUT'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function patch(string $route, $handler, ...$handlers): void
    {
        $this->map(['PATCH'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function delete(string $route, $handler, ...$handlers): void
    {
        $this->map(['DELETE'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function options(string $route, $handler, ...$handlers): void
    {
        $this->map(['OPTIONS'], $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function any(string $route, $handler, ...$handlers): void
    {
        $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route, $handler, ...$handlers);
    }

    /**
     *
     * @param string[] $methods
     * @param string $route
     * @param callable|class-string $handler
     * @param callable|class-string ...$handlers
     */
    public function map(array $methods, string $route, $handler, ...$handlers): void
    {
        $this->router->map($methods, $route, $handler, ...$handlers);
    }

    /**
     * @param string $route
     * @param string $target
     * @param int $code
     */
    public function redirect(string $route, string $target, int $code = Response::STATUS_FOUND): void
    {
        $this->any($route, new RedirectHandler($target, $code));
    }

    public function run()
    {
        if (\PHP_SAPI === 'cli') {
            $this->runLoop();
        } else {
            $this->runOnce(); // @codeCoverageIgnore
        }

        Loop::run();
    }

    private function runLoop()
    {
        $http = new HttpServer(function (ServerRequestInterface $request) {
            return $this->handleRequest($request);
        });

        $listen = $_SERVER['X_LISTEN'] ?? '127.0.0.1:8080';

        $socket = new SocketServer($listen);
        $http->listen($socket);

        $this->sapi->log('Listening on ' . \str_replace('tcp:', 'http:', $socket->getAddress()));

        $http->on('error', function (\Exception $e) {
            $orig = $e;
            $message = 'Error: ' . $e->getMessage();
            while (($e = $e->getPrevious()) !== null) {
                $message .= '. Previous: ' . $e->getMessage();
            }

            $this->sapi->log($message);

            \fwrite(STDERR, (string)$orig);
        });
    }

    private function runOnce()
    {
        $request = $this->sapi->requestFromGlobals();

        $response = $this->handleRequest($request);

        if ($response instanceof ResponseInterface) {
            $this->sapi->sendResponse($response);
        } elseif ($response instanceof PromiseInterface) {
            $response->then(function (ResponseInterface $response) {
                $this->sapi->sendResponse($response);
            });
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface|PromiseInterface<ResponseInterface,void>
     *     Returns a response or a Promise which eventually fulfills with a
     *     response. This method never throws or resolves a rejected promise.
     *     If the request can not be routed or the handler fails, it will be
     *     turned into a valid error response before returning.
     */
    private function handleRequest(ServerRequestInterface $request)
    {
        $response = ($this->handler)($request);
        if ($response instanceof \Generator) {
            if ($response->valid()) {
                $response = $this->coroutine($response);
            } else {
                $response = $response->getReturn();
            }
        }

        return $response;
    }

    private function coroutine(\Generator $generator): PromiseInterface
    {
        $next = null;
        $deferred = new Deferred();
        $next = function () use ($generator, &$next, $deferred) {
            if (!$generator->valid()) {
                $deferred->resolve($generator->getReturn());
                return;
            }

            $promise = $generator->current();
            $promise->then(function ($value) use ($generator, $next) {
                $generator->send($value);
                $next();
            }, function ($reason) use ($generator, $next) {
                $generator->throw($reason);
                $next();
            });
        };

        $next();

        return $deferred->promise();
    }
}
