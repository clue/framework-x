<?php

namespace FrameworkX;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use React\Stream\ReadableStreamInterface;

class App
{
    private $loop;
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
        if (\is_callable($loop)) {
            \array_unshift($middleware, $loop);
            $loop = null;
        } elseif (\func_num_args() !== 0 && !$loop instanceof LoopInterface) {
            throw new \TypeError('Argument 1 ($loop) must be callable|' . LoopInterface::class . ', ' . $this->describeType($loop) . ' given');
        }
        $this->loop = $loop ?? Loop::get();
        $this->middleware = $middleware;
        $this->router = new RouteCollector(new RouteParser(), new RouteGenerator());
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

        $socket = new SocketServer('127.0.0.1:8080', [], $this->loop);
        $http->listen($socket);

        $this->log('Listening on ' . \str_replace('tcp:', 'http:', $socket->getAddress()));

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
                return $this->errorMethodNotAllowed($routeInfo[1]);
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

        $this->log(
            ($request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
            '"' . $request->getMethod() . ' ' . $request->getUri()->getPath() . ' HTTP/' . $request->getProtocolVersion() . '" ' .
            $response->getStatusCode() . ' ' . $response->getBody()->getSize()
        );
    }

    private function log(string $message): void
    {
        $time = microtime(true);

        $log = date('Y-m-d H:i:s', (int)$time) . sprintf('.%03d ', (int)(($time - (int)$time) * 1e3)) . $message . PHP_EOL;

        if (\PHP_SAPI === 'cli') {
            echo $log;
        } else {
            fwrite(defined('STDERR') ? STDERR : fopen('php://stderr', 'a'), $log);
        }
    }

    private function error(int $statusCode, string $title, string ...$info): ResponseInterface
    {
        $nonce = \base64_encode(\random_bytes(16));
        $info = \implode('', \array_map(function (string $info) { return "<p>$info</p>\n"; }, $info));
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>Error $statusCode: $title</title>
<style nonce="$nonce">
body { display: grid; justify-content: center; align-items: center; grid-auto-rows: minmax(min-content, 100vh); margin: 0; font-family: ui-sans-serif, Arial, "Noto Sans", sans-serif; }
main { display: grid; max-width: 700px; margin: 2em; }
h1 { margin: 0 .5em 0 0; border-right: 2px solid #e3e4e7; padding-right: .5em; color: #aebdcc; font-size: 3em; }
strong { color: #111827; font-size: 3em; }
p { margin: .5em 0 0 0; grid-column: 2; color: #6b7280; }
code { padding: 0 .3em; background-color: #f5f6f9; }
@media (max-width: 600px) {
  main { display: block; }
  h1::before { content: "Error "; }
  h1 { border: 0; }
}
</style>
</head>
<body>
<main>
<h1>$statusCode</h1>
<strong>$title</strong>
$info</main>
</body>
</html>

HTML;

        return new Response(
            $statusCode,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Security-Policy' => "style-src 'nonce-$nonce'; img-src 'self'; default-src 'none'"
            ],
            $html
        );
    }

    private function errorProxy(): ResponseInterface
    {
        return $this->error(
            400,
            'Proxy Requests Not Allowed',
            'Please check your settings and retry.'
        );
    }

    private function errorNotFound(): ResponseInterface
    {
        return $this->error(
            404,
            'Page Not Found',
            'Please check the URL in the address bar and try again.'
        );
    }

    private function errorMethodNotAllowed(array $allowedMethods): ResponseInterface
    {
        $methods = \implode('/', \array_map(function (string $method) { return '<code>' . $method . '</code>'; }, $allowedMethods));

        return $this->error(
            405,
            'Method Not Allowed',
            'Please check the URL in the address bar and try again with ' . $methods . ' request.'
        )->withHeader('Allow', implode(', ', $allowedMethods));
    }

    private function errorHandlerException(\Throwable $e): ResponseInterface
    {
        $where = ' in <code title="See ' . $e->getFile() . ' line ' . $e->getLine() . '">' . \basename($e->getFile()) . ':' . $e->getLine() . '</code>';
        $message = '<code>' . $this->escapeHtml($e->getMessage()) . '</code>';

        return $this->error(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got uncaught <code>' . \get_class($e) . '</code> with message ' . $message . $where . '.'
        );
    }

    private function errorHandlerResponse($value): ResponseInterface
    {
        return $this->error(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
        );
    }

    private function errorHandlerCoroutine($value): ResponseInterface
    {
        return $this->error(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to yield <code>' . PromiseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
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

    private function escapeHtml(string $s): string
    {
        return \addcslashes(
            \str_replace(
                ' ',
                '&nbsp;',
                \htmlspecialchars($s, \ENT_NOQUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED, 'utf-8')
            ),
            "\0..\032\\"
        );
    }
}
