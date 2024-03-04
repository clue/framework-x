<?php

namespace FrameworkX;

use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\Io\ReactiveHandler;
use FrameworkX\Io\RedirectHandler;
use FrameworkX\Io\RouteHandler;
use FrameworkX\Io\SapiHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Async\await;

class App
{
    /** @var MiddlewareHandler */
    private $handler;

    /** @var RouteHandler */
    private $router;

    /** @var ReactiveHandler|SapiHandler */
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
        // new MiddlewareHandler([$fiberHandler, $accessLogHandler, $errorHandler, ...$middleware, $routeHandler])
        $handlers = [];

        $container = $needsErrorHandler = new Container();

        // only log for built-in webserver and PHP development webserver by default, others have their own access log
        $needsAccessLog = (\PHP_SAPI === 'cli' || \PHP_SAPI === 'cli-server') ? $container : null;

        if ($middleware) {
            $needsErrorHandlerNext = false;
            foreach ($middleware as $handler) {
                // load AccessLogHandler and ErrorHandler instance from last Container
                if ($handler === AccessLogHandler::class) {
                    $handler = $container->getAccessLogHandler();
                } elseif ($handler === ErrorHandler::class) {
                    $handler = $container->getErrorHandler();
                }

                // ensure AccessLogHandler is always followed by ErrorHandler
                if ($needsErrorHandlerNext && !$handler instanceof ErrorHandler) {
                    break;
                }
                $needsErrorHandlerNext = false;

                if ($handler instanceof Container) {
                    // remember last Container to load any following class names
                    $container = $handler;

                    // add default ErrorHandler from last Container before adding any other handlers, may be followed by other Container instances (unlikely)
                    if (!$handlers) {
                        $needsErrorHandler = $needsAccessLog = $container;
                    }
                } elseif (!\is_callable($handler)) {
                    $handlers[] = $container->callable($handler);
                } else {
                    // don't need a default ErrorHandler if we're adding one as first handler or AccessLogHandler as first followed by one
                    if ($needsErrorHandler && ($handler instanceof ErrorHandler || $handler instanceof AccessLogHandler) && !$handlers) {
                        $needsErrorHandler = null;
                    }

                    // only add to list of handlers if this is not a NOOP
                    if (!$handler instanceof AccessLogHandler || !$handler->isDevNull()) {
                        $handlers[] = $handler;
                    }

                    if ($handler instanceof AccessLogHandler) {
                        $needsAccessLog = null;
                        $needsErrorHandlerNext = true;
                    }
                }
            }
            if ($needsErrorHandlerNext) {
                throw new \TypeError('AccessLogHandler must be followed by ErrorHandler');
            }
        }

        // add default ErrorHandler as first handler unless it is already added explicitly
        if ($needsErrorHandler instanceof Container) {
            \array_unshift($handlers, $needsErrorHandler->getErrorHandler());
        }

        // only log for built-in webserver and PHP development webserver by default, others have their own access log
        if ($needsAccessLog instanceof Container) {
            $handler = $needsAccessLog->getAccessLogHandler();
            if (!$handler->isDevNull()) {
                \array_unshift($handlers, $handler);
            }
        }

        $this->router = new RouteHandler($container);
        $handlers[] = $this->router;
        $this->handler = new MiddlewareHandler($handlers);
        $this->sapi = \PHP_SAPI === 'cli' ? new ReactiveHandler($container->getEnv('X_LISTEN')) : new SapiHandler();
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
        // backward compatibility: `OPTIONS * HTTP/1.1` can be matched with empty path (legacy)
        if ($route === '') {
            $route = '*';
        }

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

    /**
     * Runs the app to handle HTTP requests according to any registered routes and middleware.
     *
     * This is where the magic happens: When executed on the command line (CLI),
     * this will run the powerful reactive request handler built on top of
     * ReactPHP. This works by running the efficient built-in HTTP web server to
     * handle incoming HTTP requests through ReactPHP's HTTP and socket server.
     * This async execution mode is usually recommended as it can efficiently
     * process a large number of concurrent connections and process multiple
     * incoming requests simultaneously. The long-running server process will
     * continue to run until it is interrupted by a signal.
     *
     * When executed behind traditional PHP SAPIs (PHP-FPM, FastCGI, Apache, etc.),
     * this will handle a single request and run until a single response is sent.
     * This is particularly useful because it allows you to run the exact same
     * app in any environment.
     *
     * @see ReactiveHandler::run()
     * @see SapiHandler::run()
     */
    public function run(): void
    {
        $this->sapi->run(\Closure::fromCallable([$this, 'handleRequest']));
    }

    /**
     * Invokes the app to handle a single HTTP request according to any registered routes and middleware.
     *
     * This method allows you to pass in a single HTTP request object that will
     * be processed according to any registered routes and middleware and will
     * return an HTTP response object as a result.
     *
     * ```php
     * $app = new FrameworkX\App();
     * $app->get('/', fn() => React\Http\Message\Response::plaintext("Hello!\n"));
     *
     * $request = new React\Http\Message\ServerRequest('GET', 'https://example.com/');
     * $response = $app($request);
     *
     * assert($response instanceof Psr\Http\Message\ResponseInterface);
     * assert($response->getStatusCode() === 200);
     * assert($response->getBody()->getContents() === "Hello\n");
     * ```
     *
     * This is particularly useful for higher-level integration test suites and
     * for custom integrations with other runtime environments like serverless
     * functions or other frameworks. Otherwise, most applications would likely
     * want to use the `run()` method to run the application and automatically
     * accept incoming HTTP requests according to the PHP SAPI in use.
     *
     * @param ServerRequestInterface $request The HTTP request object to process.
     * @return ResponseInterface This method returns an HTTP response object
     *     according to any registered routes and middleware. If any handler is
     *     async, it will await its execution before returning, running the
     *     event loop as needed. If the request can not be routed or any handler
     *     fails, it will return a matching HTTP error response object.
     * @throws void This method never throws. If the request can not be routed
     *     or any handler fails, it will be turned into a valid error response
     *     before returning.
     * @see self::run()
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->handleRequest($request);
        if ($response instanceof PromiseInterface) {
            /** @throws void */
            $response = await($response);
            assert($response instanceof ResponseInterface);
        }

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface|PromiseInterface<ResponseInterface>
     *     Returns a response or a Promise which eventually fulfills with a
     *     response. This method never throws or resolves a rejected promise.
     *     If the request can not be routed or the handler fails, it will be
     *     turned into a valid error response before returning.
     * @throws void
     */
    private function handleRequest(ServerRequestInterface $request)
    {
        $response = ($this->handler)($request);
        assert($response instanceof ResponseInterface || $response instanceof PromiseInterface || $response instanceof \Generator);

        if ($response instanceof \Generator) {
            if ($response->valid()) {
                $response = $this->coroutine($response);
            } else {
                $response = $response->getReturn();
                assert($response instanceof ResponseInterface);
            }
        }

        return $response;
    }

    /**
     * @return PromiseInterface<ResponseInterface>
     */
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
            assert($promise instanceof PromiseInterface);

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
