<?php

namespace FrameworkX\Io;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use React\Promise\PromiseInterface;
use function React\Async\async;

/**
 * @internal
 */
class PsrMiddlewareAdapter
{

    /**
     * @var PsrMiddlewareInterface
     */
    private $middleware;

    public function __construct(PsrMiddlewareInterface $middleware) {
        $this->middleware = $middleware;
    }

    /** @return PromiseInterface<ResponseInterface> */
    public function __invoke(ServerRequestInterface $request, callable $next = null): PromiseInterface
    {
        return async(function () use ($request, $next) {
            return $this->middleware->process($request, new PsrAwaitRequestHandler($next));
        })($request, $next);
    }
}
