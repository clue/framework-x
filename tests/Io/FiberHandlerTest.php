<?php

namespace Framework\Tests\Io;

use FrameworkX\Io\FiberHandler;
use PHPUnit\Framework\TestCase;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class FiberHandlerTest extends TestCase
{
    public function testInvokeWithHandlerReturningResponseReturnsSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $ret = $handler($request, function () use ($response) { return $response; });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningPromiseResolvingWithResponseReturnsPromiseResolvingWithSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) { return resolve($response); });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseReturnsGeneratorReturningSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            if (false) { // @phpstan-ignore-line
                yield;
            }
            return $response;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingResolvedPromiseReturnsGeneratorReturningSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            return yield resolve($response);
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $generator->send($response);
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingRejectedPromiseReturnsGeneratorReturningSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            try {
                yield reject(new \RuntimeException('Foo'));
            } catch (\RuntimeException $e) {
                return $response;
            }
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $generator->throw(new \RuntimeException('Foo'));
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningResponseAfterAwaitingResolvedPromiseReturnsSameResponse(): void
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $ret = $handler($request, function () use ($response) {
            return await(resolve($response));
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningResponseAfterAwaitingPendingPromiseReturnsPromiseResolvingWithSameResponse(): void
    {
        // work around lack of actual fibers in PHP < 8.1
        if (\method_exists(\Fiber::class, 'mockSuspend')) {
            \Fiber::mockSuspend();
        }

        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $deferred = new Deferred();

        $promise = $handler($request, function () use ($deferred) {
            // going the extra mile if using reactphp/async < 4 on PHP 8.1+
            if (PHP_VERSION_ID >= 80100 && !function_exists('React\Async\async')) {
                $fiber = \Fiber::getCurrent();
                assert($fiber instanceof \Fiber);
                $deferred->promise()->then(function () use ($fiber): void {
                    $fiber->resume();
                });
                \Fiber::suspend();
            }

            return await($deferred->promise());
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertNull($ret);

        $deferred->resolve($response);

        // work around lack of actual fibers in PHP < 8.1
        if (\method_exists(\Fiber::class, 'mockResume')) {
            \Fiber::mockResume();
        }

        $this->assertSame($response, $ret);
    }
}
