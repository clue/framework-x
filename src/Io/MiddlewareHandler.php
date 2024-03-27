<?php

namespace FrameworkX\Io;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * @internal
 */
class MiddlewareHandler
{
    /** @var list<callable> $handlers */
    private $handlers;

    /** @param list<callable|MiddlewareInterface> $handlers */
    public function __construct(array $handlers)
    {
        assert(count($handlers) >= 2);

        $this->handlers = array_map(function ($handler) {
            if ($handler instanceof MiddlewareInterface) {
                return new PsrMiddlewareAdapter($handler);
            }
            return $handler;
         }, $handlers);
    }

    /** @return mixed */
    public function __invoke(ServerRequestInterface $request)
    {
        return $this->call($request, 0);
    }

    /** @return mixed */
    private function call(ServerRequestInterface $request, int $position)
    {
        if (!isset($this->handlers[$position + 2])) {
            return $this->handlers[$position]($request, $this->handlers[$position + 1]);
        }

        return $this->handlers[$position]($request, function (ServerRequestInterface $request) use ($position) {
            return $this->call($request, $position + 1);
        });
    }
}
