<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;

/**
 * @internal
 */
class AccessLogHandler
{
    /** @var SapiHandler */
    private $sapi;

    /** @var bool */
    private $hasHighResolution;

    public function __construct()
    {
        $this->sapi = new SapiHandler();
        $this->hasHighResolution = \function_exists('hrtime'); // PHP 7.3+
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $now = $this->now();
        $response = $next($request);

        if ($response instanceof PromiseInterface) {
            return $response->then(function (ResponseInterface $response) use ($request, $now) {
                $this->logWhenClosed($request, $response, $now);
                return $response;
            });
        } elseif ($response instanceof \Generator) {
            return (function (\Generator $generator) use ($request, $now) {
                $response = yield from $generator;
                $this->logWhenClosed($request, $response, $now);
                return $response;
            })($response);
        } else {
            $this->logWhenClosed($request, $response, $now);
            return $response;
        }
    }

    /**
     * checks if response body is closed (not streaming) before writing log message for response
     */
    private function logWhenClosed(ServerRequestInterface $request, ResponseInterface $response, float $start): void
    {
        $body = $response->getBody();

        if ($body instanceof ReadableStreamInterface && $body->isReadable()) {
            $size = 0;
            $body->on('data', function (string $chunk) use (&$size) {
                $size += strlen($chunk);
            });

            $body->on('close', function () use (&$size, $request, $response, $start) {
                $this->log($request, $response, $size, $this->now() - $start);
            });
        } else {
            $this->log($request, $response, $body->getSize() ?? strlen((string) $body), $this->now() - $start);
        }
    }

    /**
     * writes log message for response after response body is closed (not streaming anymore)
     */
    private function log(ServerRequestInterface $request, ResponseInterface $response, int $responseSize, float $time): void
    {
        $method = $request->getMethod();
        $status = $response->getStatusCode();

        // HEAD requests and `204 No Content` and `304 Not Modified` always use an empty response body
        if ($method === 'HEAD' || $status === Response::STATUS_NO_CONTENT || $status === Response::STATUS_NOT_MODIFIED) {
            $responseSize = 0;
        }

        $this->sapi->log(
            ($request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
            '"' . $this->escape($method) . ' ' . $this->escape($request->getRequestTarget()) . ' HTTP/' . $request->getProtocolVersion() . '" ' .
            $status . ' ' . $responseSize . ' ' . sprintf('%.3F', $time < 0 ? 0 : $time)
        );
    }

    private function escape(string $s): string
    {
        return preg_replace_callback('/[\x00-\x1F\x7F-\xFF"\\\\]+/', function (array $m) {
            return str_replace('%', '\x', rawurlencode($m[0]));
        }, $s);
    }

    private function now(): float
    {
        return $this->hasHighResolution ? hrtime(true) * 1e-9 : microtime(true);
    }
}
