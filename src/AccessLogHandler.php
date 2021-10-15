<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

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
                $this->log($request, $response);
                return $response;
            });
        } elseif ($response instanceof \Generator) {
            return (function (\Generator $generator) use ($request) {
                $response = yield from $generator;
                $this->log($request, $response);
                return $response;
            })($response);
        } else {
            $this->log($request, $response);
            return $response;
        }
    }

    private function log(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $this->sapi->log(
            ($request->getServerParams()['REMOTE_ADDR'] ?? '-') . ' ' .
            '"' . $this->escape($request->getMethod()) . ' ' . $this->escape($request->getRequestTarget()) . ' HTTP/' . $request->getProtocolVersion() . '" ' .
            $response->getStatusCode() . ' ' . $response->getBody()->getSize()
        );
    }

    private function escape(string $s): string
    {
        return preg_replace_callback('/[\x00-\x1F\x7F-\xFF"\\\\]+/', function (array $m) {
            return str_replace('%', '\x', rawurlencode($m[0]));
        }, $s);
    }
}
