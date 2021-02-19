<?php

namespace Frugal;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use FastRoute\DataGenerator\GroupCountBased;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;
use React\Socket\Server as SocketServer;
use React\Stream\ReadableStreamInterface;

class App
{
    private $loop;
    private $router;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->router = new RouteCollector(new Std(), new GroupCountBased());
    }

    public function get(string $route, callable $handler): void
    {
        $this->router->get($route, $handler);
    }

    public function head(string $route, callable $handler): void
    {
        $this->router->head($route, $handler);
    }

    public function post(string $route, callable $handler): void
    {
        $this->router->post($route, $handler);
    }

    public function put(string $route, callable $handler): void
    {
        $this->router->put($route, $handler);
    }

    public function patch(string $route, callable $handler): void
    {
        $this->router->patch($route, $handler);
    }

    public function delete(string $route, callable $handler): void
    {
        $this->router->delete($route, $handler);
    }

    public function options(string $route, callable $handler): void
    {
        $this->router->addRoute(['OPTIONS'], $route, $handler);
    }

    public function any(string $route, callable $handler): void
    {
        $this->router->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $route, $handler);
    }

    public function map(array $methods, string $route, callable $handler): void
    {
        $this->router->addRoute($methods, $route, $handler);
    }

    public function group($prefix, $cb)
    {
        throw new \BadMethodCallException();
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

    public function cgi(string $route, string $path)
    {
        if (\php_sapi_name() === 'cli') {
            throw new \BadMethodCallException();
        }

        $this->any(
            $route,
            function (ServerRequestInterface $request) use ($path){
                $body = '';
                set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$body) {
                    if ($errno === E_WARNING) {
                        $body .= 'PHP Warning: ';
                    } elseif ($errno === E_NOTICE) {
                        $body .= 'PHP Notice: ';
                    } else {
                        $body .= 'PHP Error: ';
                    }
                    $body .= $errstr . ' in ' . $errfile . ' on line ' . $errline . PHP_EOL;

                    ob_start();
                    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                    $body .= addslashes(ob_get_clean());
                });
                ob_start();
                $ret = include $path;
                $body .= ob_get_clean();

                $headers = array();
                foreach (headers_list() as $line) {
                    $parts = explode(': ', $line, 2);
                    $headers[$parts[0]] = $parts[1];
                }

                return new Response(
                    $ret === false ? 500 : http_response_code(),
                    $headers,
                    $body
                );
            }
        );
    }

    public function fastcgi(string $route, string $socket)
    {
        throw new \BadMethodCallException();
    }

    public function run()
    {
        if (\php_sapi_name() === 'cli') {
            $this->runLoop();
        } else {
            $this->runOnce();
        }
    }

    private function runLoop()
    {
        $dispatcher = new \FastRoute\Dispatcher\GroupCountBased($this->router->getData());

        $http = new HttpServer($this->loop, function (ServerRequestInterface $request) use ($dispatcher) {
            $response = $this->handleRequest($request, $dispatcher);

            if ($response instanceof ResponseInterface) {
                $this->logRequestResponse($request, $response);
            } elseif ($response instanceof PromiseInterface) {
                $response->then(function (ResponseInterface $response) use ($request) {
                    $this->logRequestResponse($request, $response);
                });
            }

            return $response;
        });

        $socket = new SocketServer(8080, $this->loop);
        $http->listen($socket);

        $this->log('Listening on ' . $socket->getAddress());

        $http->on('error', function (\Exception $e) {
            $orig = $e;
            $message = 'Error: ' . $e->getMessage();
            while (($e = $e->getPrevious()) !== null) {
                $message .= '. Previous: ' . $e->getMessage();
            }

            $this->log($message);

            fwrite(STDERR, (string)$orig);
        });
    }

    private function requestFromGlobals(): ServerRequestInterface
    {
        $host = null;
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (\strpos($key, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$key] = $value;

                if ($host === null && $key === 'Host') {
                    $host = $value;
                }
            }
        }

        // Content-Length / Content-Type are special <3
        if (!isset($headers['Content-Length']) && isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        if (!isset($headers['Content-Type']) && isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
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

        return $request;
    }

    private function runOnce()
    {
        $request = $this->requestFromGlobals();

        $dispatcher = new \FastRoute\Dispatcher\GroupCountBased($this->router->getData());

        $response = $this->handleRequest($request, $dispatcher);

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
     * @param Dispatcher $dispatcher
     * @return ResponseInterface|PromiseInterface<ResponseInterface>
     */
    private function handleRequest(ServerRequestInterface $request, Dispatcher $dispatcher)
    {
        if (\strpos($request->getRequestTarget(), '://') !== false || $request->getMethod() === 'CONNECT') {
            return $this->errorProxy($request);
        }

        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
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
}
