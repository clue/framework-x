<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;

/**
 * @internal
 */
class AccessLogHandler
{
    /** @var SapiHandler */
    private $sapi;

    public function __construct()
    {
        $this->sapi = new SapiHandler();
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $response = $next($request);

        if ($response instanceof PromiseInterface) {
            return $response->then(function (ResponseInterface $response) use ($request) {
                $this->logWhenClosed($request, $response);
                return $response;
            });
        } elseif ($response instanceof \Generator) {
            return (function (\Generator $generator) use ($request) {
                $response = yield from $generator;
                $this->logWhenClosed($request, $response);
                return $response;
            })($response);
        } else {
            $this->logWhenClosed($request, $response);
            return $response;
        }
    }

    /**
     * checks if response body is closed (not streaming) before writing log message for response
     */
    private function logWhenClosed(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body instanceof ReadableStreamInterface && $body->isReadable()) {
            $size = 0;
            $body->on('data', function (string $chunk) use (&$size) {
                $size += strlen($chunk);
            });

            $body->on('close', function () use (&$size, $request, $response) {
                $this->log($request, $response, $size);
            });
        } else {
            $this->log($request, $response, $body->getSize() ?? strlen((string) $body));
        }
    }

    /**
     * writes log message for response after response body is closed (not streaming anymore)
     */
    private function log(ServerRequestInterface $request, ResponseInterface $response, int $responseSize): void
    {
        $this->sapi->log(
            ($request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
            '"' . $this->escape($request->getMethod()) . ' ' . $this->escape($request->getRequestTarget()) . ' HTTP/' . $request->getProtocolVersion() . '" ' .
            $response->getStatusCode() . ' ' . $responseSize
        );
    }

    private function escape(string $s): string
    {
        return preg_replace_callback('/[\x00-\x1F\x7F-\xFF"\\\\]+/', function (array $m) {
            return str_replace('%', '\x', rawurlencode($m[0]));
        }, $s);
    }
}
