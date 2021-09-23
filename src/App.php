<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;

class App
{
    private $loop;

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
     *
     * // instantiate with optional $loop
     * $app = new App($loop);
     * $app = new App($loop, $middleware);
     * $app = new App($loop, $middleware1, $middleware2);
     *
     * // invalid $loop argument
     * $app = new App(null);
     * $app = new App(null, $middleware);
     * ```
     *
     * @param callable|LoopInterface|null $loop
     * @param callable ...$middleware
     * @throws \TypeError if given $loop argument is invalid
     */
    public function __construct($loop = null, callable ...$middleware)
    {
        $errorHandler = new ErrorHandler();
        if (\is_callable($loop)) {
            \array_unshift($middleware, $loop);
            $loop = null;
        } elseif (\func_num_args() !== 0 && !$loop instanceof LoopInterface) {
            throw new \TypeError('Argument 1 ($loop) must be callable|' . LoopInterface::class . ', ' . $errorHandler->describeType($loop) . ' given');
        }

        $this->loop = $loop ?? Loop::get();
        $this->router = new RouteHandler();

        // new MiddlewareHandler([$errorHandler, ...$middleware, $routeHandler])
        \array_unshift($middleware, $errorHandler);
        $middleware[] = $this->router;
        $this->handler = new MiddlewareHandler($middleware);
        $this->sapi = new SapiHandler();
    }

    public function get(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['GET'], $route, $handler, ...$handlers);
    }

    public function head(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['HEAD'], $route, $handler, ...$handlers);
    }

    public function post(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['POST'], $route, $handler, ...$handlers);
    }

    public function put(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['PUT'], $route, $handler, ...$handlers);
    }

    public function patch(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['PATCH'], $route, $handler, ...$handlers);
    }

    public function delete(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['DELETE'], $route, $handler, ...$handlers);
    }

    public function options(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['OPTIONS'], $route, $handler, ...$handlers);
    }

    public function any(string $route, callable $handler, callable ...$handlers): void
    {
        $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route, $handler, ...$handlers);
    }

    public function map(array $methods, string $route, callable $handler, callable ...$handlers): void
    {
        $this->router->map($methods, $route, $handler, ...$handlers);
    }

    public function redirect(string $route, string $target, int $code = 302): void
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

        $this->loop->run();
    }

    private function runLoop()
    {
        $http = new HttpServer($this->loop, function (ServerRequestInterface $request) {
            $response = $this->handleRequest($request);

            if ($response instanceof ResponseInterface) {
                $this->sapi->logRequestResponse($request, $response);
            } elseif ($response instanceof PromiseInterface) {
                $response->then(function (ResponseInterface $response) use ($request) {
                    $this->sapi->logRequestResponse($request, $response);
                });
            }

            return $response;
        });

        $listen = \getenv('X_LISTEN');
        if ($listen === false) {
            $listen = '127.0.0.1:8080';
        }

        $socket = new SocketServer($listen, [], $this->loop);
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
            $this->sapi->logRequestResponse($request, $response);
            $this->sapi->sendResponse($response);
        } elseif ($response instanceof PromiseInterface) {
            $response->then(function (ResponseInterface $response) use ($request) {
                $this->sapi->logRequestResponse($request, $response);
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
