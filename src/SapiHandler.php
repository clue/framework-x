<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Stream\ReadableStreamInterface;

/**
 * @internal
 */
class SapiHandler
{
    /** @var resource */
    private $logStream;

    public function __construct()
    {
        $this->logStream = PHP_SAPI === 'cli' ? \fopen('php://output', 'a') : (\defined('STDERR') ? \STDERR : \fopen('php://stderr', 'a'));
    }

    public function requestFromGlobals(): ServerRequestInterface
    {
        $host = null;
        $headers = array();
        // @codeCoverageIgnoreStart
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
        // @codeCoverageIgnoreEnd

        $target = ($_SERVER['REQUEST_URI'] ?? '/');
        $url = $target;
        if (($target[0] ?? '/') === '/' || $target === '*') {
            $url = ($_SERVER['HTTPS'] ?? null === 'on' ? 'https://' : 'http://') . ($host ?? 'localhost') . ($target === '*' ? '' : $target);
        }

        $body = file_get_contents('php://input');

        $request = new ServerRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $url,
            $headers,
            $body,
            substr($_SERVER['SERVER_PROTOCOL'] ?? 'http/1.1', 5),
            $_SERVER
        );
        if ($host === null) {
            $request = $request->withoutHeader('Host');
        }
        if (isset($target[0]) && $target[0] !== '/') {
            $request = $request->withRequestTarget($target);
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

    /**
     * @param ResponseInterface $response
     */
    public function sendResponse(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $body = $response->getBody();

        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . $response->getReasonPhrase());

        if ($status === Response::STATUS_NO_CONTENT) {
            // `204 No Content` MUST NOT include "Content-Length" response header
            $response = $response->withoutHeader('Content-Length');
        } elseif (!$response->hasHeader('Content-Length') && $body->getSize() !== null && ($status !== Response::STATUS_NOT_MODIFIED || $body->getSize() !== 0)) {
            // automatically assign "Content-Length" response header if known and not already present
            $response = $response->withHeader('Content-Length', (string) $body->getSize());
        }

        // remove default "Content-Type" header set by PHP (default_mimetype)
        if (!$response->hasHeader('Content-Type')) {
            header('Content-Type:');
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

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD' || $status === Response::STATUS_NO_CONTENT || $status === Response::STATUS_NOT_MODIFIED) {
            $body->close();
            return;
        }

        if ($body instanceof ReadableStreamInterface) {
            // try to disable nginx buffering (http://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_buffering)
            if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') === 0) {
                header('X-Accel-Buffering: no');
            }

            // clear output buffer to show streaming output (default in cli-server)
            if (\PHP_SAPI === 'cli-server') {
                \ob_end_flush(); // @codeCoverageIgnore
            }

            // flush data whenever stream reports one data chunk
            $body->on('data', function ($chunk) {
                echo $chunk;
                flush();
            });
        } else {
            echo $body;
        }
    }

    public function log(string $message): void
    {
        $time = microtime(true);
        $log = date('Y-m-d H:i:s', (int)$time) . sprintf('.%03d ', (int)(($time - (int)$time) * 1e3)) . $message . PHP_EOL;

        fwrite($this->logStream, $log);
    }
}
