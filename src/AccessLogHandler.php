<?php

namespace FrameworkX;

use FrameworkX\Io\LogStreamHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;

/**
 * @final
 */
class AccessLogHandler
{
    /** @var ?LogStreamHandler */
    private $logger;

    /** @var bool */
    private $hasHighResolution;

    /**
     * @param ?string $path (optional) absolute log file path or will log to console output by default
     * @throws \InvalidArgumentException if given `$path` is not an absolute file path
     * @throws \RuntimeException if given `$path` can not be opened in append mode
     */
    public function __construct(?string $path = null)
    {
        if ($path === null) {
            $path = \PHP_SAPI === 'cli' ? 'php://output' : 'php://stderr';
        }

        $logger = new LogStreamHandler($path);
        if (!$logger->isDevNull()) {
            // only assign logger if we're not logging to /dev/null (which would discard any logs)
            $this->logger = $logger;
        }

        $this->hasHighResolution = \function_exists('hrtime'); // PHP 7.3+
    }

    /**
     * [Internal] Returns whether we're writing to /dev/null (which will discard any logs)
     *
     * @internal
     * @return bool
     */
    public function isDevNull(): bool
    {
        return $this->logger === null;
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface>|\Generator
     */
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        if ($this->logger === null) {
            // Skip if we're logging to /dev/null (which will discard any logs).
            // As an additional optimization, the `App` will automatically
            // detect we no longer need to invoke this instance at all.
            return $next($request); // @codeCoverageIgnore
        }

        $now = $this->now();
        $response = $next($request);

        if ($response instanceof PromiseInterface) {
            /** @var PromiseInterface<ResponseInterface> $response */
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

        \assert($this->logger instanceof LogStreamHandler);
        $this->logger->log(
            ($request->getAttribute('remote_addr') ?? $request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
            '"' . $this->escape($method) . ' ' . $this->escape($request->getRequestTarget()) . ' HTTP/' . $request->getProtocolVersion() . '" ' .
            $status . ' ' . $responseSize . ' ' . sprintf('%.3F', $time < 0 ? 0 : $time)
        );
    }

    private function escape(string $s): string
    {
        return (string) preg_replace_callback('/[\x00-\x1F\x7F-\xFF"\\\\]+/', function (array $m) {
            return str_replace('%', '\x', rawurlencode($m[0]));
        }, $s);
    }

    private function now(): float
    {
        return $this->hasHighResolution ? hrtime(true) * 1e-9 : microtime(true);
    }
}
