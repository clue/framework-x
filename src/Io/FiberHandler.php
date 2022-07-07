<?php

namespace FrameworkX\Io;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * [Internal] Fibers middleware handler to ensure each request is processed in a separate `Fiber`
 *
 * The `Fiber` class has been added in PHP 8.1+, so this middleware is only used
 * on PHP 8.1+. On supported PHP versions, this middleware is automatically
 * added to the list of middleware handlers, so there's no need to reference
 * this class in application code.
 *
 * @internal
 * @link https://framework-x.org/docs/async/fibers/
 */
class FiberHandler
{
    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface,void>|\Generator
     *     Returns a `ResponseInterface` from the next request handler in the
     *     chain. If the next request handler returns immediately, this method
     *     will return immediately. If the next request handler suspends the
     *     fiber (see `await()`), this method will return a `PromiseInterface`
     *     that is fulfilled with a `ResponseInterface` when the fiber is
     *     terminated successfully. If the next request handler returns a
     *     promise, this method will return a promise that follows its
     *     resolution. If the next request handler returns a Generator-based
     *     coroutine, this method returns a `Generator`. This method never
     *     throws or resolves a rejected promise. If the handler fails, it will
     *     be turned into a valid error response before returning.
     * @throws void
     */
    public function __invoke(ServerRequestInterface $request, callable $next): mixed
    {
        $deferred = null;
        $fiber = new \Fiber(function () use ($request, $next, &$deferred) {
            $response = $next($request);
            assert($response instanceof ResponseInterface || $response instanceof PromiseInterface || $response instanceof \Generator);

            if ($deferred !== null) {
                $deferred->resolve($response);
            }

            return $response;
        });

        $fiber->start();
        if ($fiber->isTerminated()) {
            return $fiber->getReturn();
        }

        $deferred = new Deferred();
        return $deferred->promise();
    }
}
