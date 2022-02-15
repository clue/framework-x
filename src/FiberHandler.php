<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
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
     * @return PromiseInterface<ResponseInterface,void>
     *     Returns a promise that is fulfilled with a `ResponseInterface` on
     *     success. This method never throws or resolves a rejected promise.
     *     If the request can not be routed or the handler fails, it will be
     *     turned into a valid error response before returning.
     * @throws void
     */
    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        return new Promise(function ($resolve) use ($next, $request) {
            $fiber = new \Fiber(function () use ($resolve, $next, $request) {
                $response = $next($request);
                if ($response instanceof \Generator) {
                    $response = $this->coroutine($response);
                }

                $resolve($response);
            });
            $fiber->start();
        });
    }

    private function coroutine(\Generator $generator): PromiseInterface
    {
        $next = null;
        $deferred = new Deferred();
        $next = function () use ($generator, &$next, $deferred) {
            if (!$generator->valid()) {
                $deferred->resolve($generator->getReturn());
                return;
            }

            $promise = $generator->current();
            $promise->then(function ($value) use ($generator, $next) {
                $generator->send($value);
                $next();
            }, function ($reason) use ($generator, $next) {
                $generator->throw($reason);
                $next();
            });
        };

        $next();

        return $deferred->promise();
    }
}
