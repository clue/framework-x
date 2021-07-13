<?php

namespace FrameworkX;

use DI\Container;
use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Http\Server as HttpServer;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Server as SocketServer;
use React\Stream\ReadableStreamInterface;

class App
{
    private Container $container;
    private LoopInterface $loop;
    private ?LoggerInterface $logger;
    private $middleware;
    private $router;
    private $routeDispatcher;

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
     * // instantiate with optional $container
     * $app = new App($container);
     * $app = new App($container, $middleware);
     * $app = new App($container, $middleware1, $middleware2);
     *
     * // invalid $container argument
     * $app = new App(null);
     * $app = new App(null, $middleware);
     * ```
     *
     * @param callable|LoopInterface|null $loop
     * @param callable ...$middleware
     * @throws \TypeError if given $loop argument is invalid
     */
    public function __construct($container = null, callable ...$middleware)
    {
        if (\is_callable($container)) {
            \array_unshift($middleware, $container);
            $container = null;
        } elseif (\func_num_args() !== 0 && !$container instanceof ContainerInterface) {
            throw new \TypeError('Argument 1 ($container) must be callable|' . ContainerInterface::class . ', ' . $this->describeType($container) . ' given');
        }

        $this->container  = $container ?: new Container();
        $this->loop       = $this->container->has('loop') ? $this->container->get('loop') : Loop::get();
        $this->logger     = $this->container->has('logger') ? $this->container->get('logger') : null;
        $this->middleware = $middleware;
        $this->router     = new RouteCollector(new RouteParser(), new RouteGenerator());
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
        if ($handlers) {
            $handler = new MiddlewareHandler(array_merge([$handler], $handlers));
        }

        $this->routeDispatcher = null;
        $this->router->addRoute($methods, $route, $handler);
    }

    public function redirect($route, $target, $code = 302)
    {
        return $this->get($route, function () use ($target, $code) {
            return new Response(
                $code,
                [
                    'Content-Type' => 'text/html',
                    'Location' => $target
                ],
                'See ' . $target . '...' . "\n"
            );
        });
    }

    public function run()
    {
        if (\php_sapi_name() === 'cli') {
            $this->runLoop();
        } else {
            $this->runOnce();
        }

        $this->loop->run();
    }

    private function runLoop()
    {
        $http = new HttpServer($this->loop, function (ServerRequestInterface $request) {
            $response = $this->handleRequest($request);

            if ($response instanceof ResponseInterface) {
                $this->logRequestResponse($request, $response);
            } elseif ($response instanceof PromiseInterface) {
                $response->then(function (ResponseInterface $response) use ($request) {
                    $this->logRequestResponse($request, $response);
                });
            }

            return $response;
        });

        $context = $this->container->has('socketServerContext') ? $this->container->get('socketServerContext') : [];

        $uri = !empty($context['uri']) ? $context['uri'] : 8080;
        unset($context['uri']);

        $socket = new SocketServer($uri, $this->loop, $context);
        $http->listen($socket);

        $this->log('Listening on ' . \str_replace(['tcp:', 'tls:'], ['http:', 'https'], $socket->getAddress()));

        $http->on('error', function (\Exception $e) {
            $orig = $e;
            $message = 'Error: ' . $e->getMessage();
            while (($e = $e->getPrevious()) !== null) {
                $message .= '. Previous: ' . $e->getMessage();
            }

            $this->log($message);

            \fwrite(STDERR, (string)$orig);
        });
    }

    private function requestFromGlobals(): ServerRequestInterface
    {
        $host = null;
        $headers = array();
        if (\function_exists('getallheaders')) {
            $headers = \getallheaders();
            $host = \array_change_key_case($headers, \CASE_LOWER)['host'] ?? null;
        } else {
            foreach ($_SERVER as $key => $value) {
                if (\strpos($key, 'HTTP_') === 0) {
                    $key = str_replace(' ', '-', \ucwords(\strtolower(\str_replace('_', ' ', \substr($key, 5)))));
                    $headers[$key] = $value;

                    if ($host === null && $key === 'Host') {
                        $host = $value;
                    }
                }
            }
        }

        $body = file_get_contents('php://input');

        $request = new ServerRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ($_SERVER['HTTPS'] ?? null === 'on' ? 'https://' : 'http://') . ($host ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'),
            $headers,
            $body,
            substr($_SERVER['SERVER_PROTOCOL'] ?? 'http/1.1', 5),
            $_SERVER
        );
        if ($host === null) {
            $request = $request->withoutHeader('Host');
        }
        $request = $request->withParsedBody($_POST);

        // Content-Length / Content-Type are special <3
        if ($request->getHeaderLine('Content-Length') === '') {
            $request = $request->withoutHeader('Content-Length');
        }
        if ($request->getHeaderLine('Content-Type') === '' && !isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $request = $request->withoutHeader('Content-Type');
        }

        return $request;
    }

    private function runOnce()
    {
        $request = $this->requestFromGlobals();

        $response = $this->handleRequest($request);

        if ($response instanceof ResponseInterface) {
            $this->sendResponse($request, $response);
        } elseif ($response instanceof PromiseInterface) {
            $response->then(function (ResponseInterface $response) use ($request) {
                $this->sendResponse($request, $response);
            });
        }
    }

    private function sendResponse(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->logRequestResponse($request, $response);

        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());

        // automatically assign "Content-Length" response header if known and not already present
        if (!$response->hasHeader('Content-Length') && $response->getBody()->getSize() !== null) {
            $response = $response->withHeader('Content-Length', (string)$response->getBody()->getSize());
        }

        // remove default "Content-Type" header set by PHP (default_mimetype)
        if (!$response->hasHeader('Content-Type')) {
            header('Content-Type: foo');
            header_remove('Content-Type');
        }

        // send all headers without applying default "; charset=utf-8" set by PHP (default_charset)
        $old = ini_get('default_charset');
        ini_set('default_charset', '');
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value);
            }
        }
        ini_set('default_charset', $old);

        $body = $response->getBody();

        if ($body instanceof ReadableStreamInterface) {
            // clear all output buffers (default in cli-server)
            while (ob_get_level()) {
                ob_end_clean();
            }

            // try to disable nginx buffering (http://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_buffering)
            if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') === 0) {
                header('X-Accel-Buffering: no');
            }

            // flush data whenever stream reports one data chunk
            $body->on('data', function ($chunk) {
                echo $chunk;
                flush();
            });
        } else {
            echo $response->getBody();
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
        $handler = function (ServerRequestInterface $request) {
            return $this->routeRequest($request);
        };
        if ($this->middleware) {
            $handler = new MiddlewareHandler(array_merge($this->middleware, [$handler]));
        }

        try {
            $response = $handler($request);
        } catch (\Throwable $e) {
            return $this->errorHandlerException($e);
        }

        if ($response instanceof \Generator) {
            $response = $this->coroutine($response);
        }

        if ($response instanceof ResponseInterface) {
            return $response;
        } elseif ($response instanceof PromiseInterface) {
            return $response->then(function ($response) {
                if (!$response instanceof ResponseInterface) {
                    return $this->errorHandlerResponse($response);
                }
                return $response;
            }, function ($e) {
                if ($e instanceof \Throwable) {
                    return $this->errorHandlerException($e);
                } else {
                    return $this->errorHandlerResponse(\React\Promise\reject($e));
                }
            });
        } else {
            return $this->errorHandlerResponse($response);
        }
    }

    private function routeRequest(ServerRequestInterface $request)
    {
        if (\strpos($request->getRequestTarget(), '://') !== false || $request->getMethod() === 'CONNECT') {
            return $this->errorProxy($request);
        }

        if ($this->routeDispatcher === null) {
            $this->routeDispatcher = new RouteDispatcher($this->router->getData());
        }

        $routeInfo = $this->routeDispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                return $this->errorNotFound($request);
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];

                return $this->errorMethodNotAllowed(
                    $request->withAttribute('allowed', $allowedMethods)
                );
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                foreach ($vars as $key => $value) {
                    $request = $request->withAttribute($key, rawurldecode($value));
                }

                return $handler($request);
        }
    } // @codeCoverageIgnore

    private function coroutine(\Generator $generator): PromiseInterface
    {
        $next = null;
        $deferred = new Deferred();
        $next = function () use ($generator, &$next, $deferred) {
            if (!$generator->valid()) {
                $deferred->resolve($generator->getReturn());
                return;
            }

            $step = $generator->current();
            if (!$step instanceof PromiseInterface) {
                $generator = $next = null;
                $deferred->resolve($this->errorHandlerCoroutine($step));
                return;
            }

            $step->then(function ($value) use ($generator, $next) {
                $generator->send($value);
                $next();
            }, function ($reason) use ($generator, $next) {
                $generator->throw($reason);
                $next();
            })->then(null, function ($e) use ($deferred) {
                $deferred->reject($e);
            });
        };

        $next();

        return $deferred->promise();
    }

    private function logRequestResponse(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // only log for built-in webserver and PHP development webserver, others have their own access log
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
            return; // @codeCoverageIgnore
        }

        $entry = ($request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
          '"' . $request->getMethod() . ' ' . $request->getUri()->getPath() . ' HTTP/' . $request->getProtocolVersion() . '" ' .
          $response->getStatusCode() . ' ' . $response->getBody()->getSize();

        $logger = $request->getAttribute('logger');

        if ($logger instanceof LoggerInterface) {
            $logger->info($entry);

            return;
        }

        $this->log($entry);
    }

    private function log(string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->notice($message);

            return;
        }

        $time = microtime(true);

        $log = date('Y-m-d H:i:s', (int)$time) . sprintf('.%03d ', (int)(($time - (int)$time) * 1e3)) . $message . PHP_EOL;

        if (\PHP_SAPI === 'cli') {
            echo $log;
        } else {
            fwrite(defined('STDERR') ? STDERR : fopen('php://stderr', 'a'), $log);
        }
    }

    private function error(int $statusCode, ?string $info = null): ResponseInterface
    {
        $response = new Response(
            $statusCode,
            [
                'Content-Type' => 'text/html'
            ],
            (string)$statusCode
        );

        $body = $response->getBody();
        $body->seek(0, SEEK_END);

        $reason = $response->getReasonPhrase();
        if ($reason !== '') {
            $body->write(' (' . $reason . ')');
        }

        if ($info !== null) {
            $body->write(': ' . $info);
        }
        $body->write("\n");

        return $response;
    }

    private function errorProxy(): ResponseInterface
    {
        return $this->error(
            400,
            'Proxy requests not allowed'
        );
    }

    private function errorNotFound(): ResponseInterface
    {
        return $this->error(404);
    }

    private function errorMethodNotAllowed(ServerRequestInterface $request): ResponseInterface
    {
        return $this->error(
            405,
            implode(', ', $request->getAttribute('allowed'))
        )->withHeader('Allowed', implode(', ', $request->getAttribute('allowed')));
    }

    private function errorHandlerException(\Throwable $e): ResponseInterface
    {
        $where = ' (<code title="See ' . $e->getFile() . ' line ' . $e->getLine() . '">' . \basename($e->getFile()) . ':' . $e->getLine() . '</code>)';

        return $this->error(
            500,
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got uncaught <code>' . \get_class($e) . '</code>' . $where . ': ' . $e->getMessage()
        );
    }

    private function errorHandlerResponse($value): ResponseInterface
    {
        return $this->error(
            500,
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>'
        );
    }

    private function errorHandlerCoroutine($value): ResponseInterface
    {
        return $this->error(
            500,
            'Expected request handler to yield <code>' . PromiseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>'
        );
    }

    private function describeType($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (\is_scalar($value) && !\is_string($value)) {
            return \var_export($value, true);
        }
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }
}
