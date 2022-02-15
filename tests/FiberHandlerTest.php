<?php

namespace Framework\Tests;

use FrameworkX\FiberHandler;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class FiberHandlerTest extends TestCase
{
    public function setUp(): void
    {
        if (PHP_VERSION_ID < 80100 || !function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires PHP 8.1+ with react/async 4+');
        }
    }

    public function testInvokeWithHandlerReturningResponseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) { return $response; });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningPromiseResolvingWithResponseReturnsPromiseResolvingWithSameResponse()
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

    public function testInvokeWithHandlerReturningGeneratorReturningResponseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) {
            if (false) {
                yield;
            }
            return $response;
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingResolvedPromiseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) {
            return yield resolve($response);
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingRejectedPromiseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) {
            try {
                yield reject(new \RuntimeException('Foo'));
            } catch (\RuntimeException $e) {
                return $response;
            }
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningResponseAfterAwaitingResolvedPromiseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $promise = $handler($request, function () use ($response) {
            return await(resolve($response));
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $ret = null;
        $promise->then(function ($value) use (&$ret) {
            $ret = $value;
        });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningResponseAfterAwaitingPendingPromiseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $deferred = new Deferred();

        $promise = $handler($request, function () use ($deferred) {
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

        // await next tick: https://github.com/reactphp/async/issues/27
        await(new Promise(function ($resolve) {
            Loop::futureTick($resolve);
        }));

        $this->assertSame($response, $ret);
    }
}
