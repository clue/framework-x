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
    public function setUp(): void
    {
        if (PHP_VERSION_ID < 80100 || !function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires PHP 8.1+ with react/async 4+');
        }
    }

    public function testInvokeWithHandlerReturningResponseReturnsSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $ret = $handler($request, function () use ($response) { return $response; });

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

    public function testInvokeWithHandlerReturningGeneratorReturningResponseReturnsGeneratorReturningSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            if (false) {
                yield;
            }
            return $response;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingResolvedPromiseReturnsGeneratorReturningSameResponse()
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

    public function testInvokeWithHandlerReturningGeneratorReturningResponseAfterYieldingRejectedPromiseReturnsGeneratorReturningSameResponse()
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

    public function testInvokeWithHandlerReturningResponseAfterAwaitingResolvedPromiseReturnsSameResponse()
    {
        $handler = new FiberHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $ret = $handler($request, function () use ($response) {
            return await(resolve($response));
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

        $this->assertSame($response, $ret);
    }
}
