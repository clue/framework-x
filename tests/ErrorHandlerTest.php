<?php

namespace FrameworkX\Tests;

use FrameworkX\ErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\PromiseInterface;
use function React\Promise\reject;
use function React\Promise\resolve;

class ErrorHandlerTest extends TestCase
{
    public function testInvokeWithHandlerReturningResponseReturnsSameResponse()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $ret = $handler($request, function () use ($response) { return $response; });

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningPromiseResolvingWithResponseReturnsPromiseResolvingWithSameResponse()
    {
        $handler = new ErrorHandler();

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

    public function testInvokeWithHandlerReturningGeneratorReturningResponseReturnsGeneratorYieldingSameResponse()
    {
        $handler = new ErrorHandler();

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

    public function testInvokeWithHandlerReturningGeneratorYieldingResolvedPromiseThenReturningResponseReturnsGeneratorYieldingSameResponse()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            yield resolve(null);

            return $response;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $generator->next();
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerReturningGeneratorYieldingRejectedPromiseInTryCatchThenReturningResponseReturnsGeneratorYieldingSameResponse()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response();

        $generator = $handler($request, function () use ($response) {
            try {
                yield reject(new \RuntimeException());
            } catch (\RuntimeException $e) {
                return $response;
            }
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $promise = $generator->current();

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $e = null;
        $promise->then(null, function ($reason) use (&$e) {
            $e = $reason;
        });

        /** @var \RuntimeException $e */
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $generator->throw($e);
        $ret = $generator->getReturn();

        $this->assertSame($response, $ret);
    }

    public function testInvokeWithHandlerThrowingExceptionReturnsError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $response = $handler($request, function () {
            throw new \RuntimeException();
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningPromiseRejectingWithExceptionReturnsPromiseResolvingWithError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $promise = $handler($request, function () {
            return reject(new \RuntimeException());
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningGeneratorThrowingExceptionReturnsGeneratorYieldingError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $generator = $handler($request, function () {
            if (false) {
                yield;
            }
            throw new \RuntimeException();
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $response = $generator->getReturn();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningGeneratorYieldingPromiseThenThrowingExceptionReturnsGeneratorYieldingError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $generator = $handler($request, function () {
            yield resolve(null);
            throw new \RuntimeException();
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $generator->next();
        $response = $generator->getReturn();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningGeneratorYieldingPromiseRejectingWithExceptionReturnsGeneratorYieldingError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $generator = $handler($request, function () {
            yield reject(new \RuntimeException());
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $promise = $generator->current();

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $e = null;
        $promise->then(null, function ($reason) use (&$e) {
            $e = $reason;
        });

        /** @var \RuntimeException $e */
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $generator->throw($e);
        $response = $generator->getReturn();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningNullReturnsError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $response = $handler($request, function () {
            return null;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningPromiseResolvingWithNullReturnsPromiseResolvingWithError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $promise = $handler($request, function () {
            return resolve(null);
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningPromiseRejectingWithNullReturnsPromiseResolvingWithError500Response()
    {
        if (method_exists(PromiseInterface::class, 'catch')) {
            $this->markTestSkipped('Only supported for legacy Promise v2, Promise v3 always rejects with Throwable');
        }

        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $promise = $handler($request, function () {
            return reject(null);
        });

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningGeneratorYieldingNullReturnsGeneratorYieldingError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $generator = $handler($request, function () {
            yield null;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $response = $generator->getReturn();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithHandlerReturningGeneratorReturningNullReturnsGeneratorYieldingError500Response()
    {
        $handler = new ErrorHandler();

        $request = new ServerRequest('GET', 'http://example.com/');

        $generator = $handler($request, function () {
            if (false) {
                yield;
            }
            return null;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);
        $response = $generator->getReturn();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testRequestNotFoundReturnsError404()
    {
        $handler = new ErrorHandler();
        $response = $handler->requestNotFound();

        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<style nonce=\"%s\">\n%a", (string) $response->getBody());
        $this->assertStringMatchesFormat('style-src \'nonce-%s\'; img-src \'self\'; default-src \'none\'', $response->getHeaderLine('Content-Security-Policy'));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again.</p>\n", (string) $response->getBody());
    }

    public function testRequestMethodNotAllowedReturnsError405WithSingleAllowedMethod()
    {
        $handler = new ErrorHandler();
        $response = $handler->requestMethodNotAllowed(['GET']);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code> request.</p>\n", (string) $response->getBody());
    }

    public function testRequestMethodNotAllowedReturnsError405WithMultipleAllowedMethods()
    {
        $handler = new ErrorHandler();
        $response = $handler->requestMethodNotAllowed(['GET', 'HEAD', 'POST']);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET, HEAD, POST', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code>/<code>HEAD</code>/<code>POST</code> request.</p>\n", (string) $response->getBody());
    }

    public function testRequestProxyUnsupportedReturnsError400()
    {
        $handler = new ErrorHandler();
        $response = $handler->requestProxyUnsupported();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString("<title>Error 400: Proxy Requests Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check your settings and retry.</p>\n", (string) $response->getBody());
    }

    public function provideExceptionMessage()
    {
        return [
            [
                'Foo',
                'Foo'
            ],
            [
                'Ünicöde!',
                'Ünicöde!'
            ],
            [
                'just some text',
                'just some text'
            ],
            [
                ' trai ling ',
                '&nbsp;trai ling&nbsp;'
            ],
            [
                'excess    ive',
                'excess &nbsp; &nbsp;ive'
            ],
            [
                "new\n line",
                'new<span>\n</span> line'
            ],
            [
                'sla/she\'s\\n',
                'sla/she\'s\\n'
            ],
            [
                "hello\r\nworld",
                'hello<span>\r\n</span>world'
            ],
            [
                '"with"<html>',
                '"with"&lt;html&gt;'
            ],
            [
                "bin\0\1\2\3\4\5\6\7ary",
                "bin��������ary"
            ],
            [
                "hell\xF6!",
                "hell�!"
            ]
        ];
    }

    /**
     * @dataProvider provideExceptionMessage
     */
    public function testErrorInvalidExceptionReturnsError500(string $in, string $expected)
    {
        $handler = new ErrorHandler();

        $line = __LINE__ + 1;
        $e = new \RuntimeException($in);

        // $response = $handler->errorInvalidException($e);
        $ref = new \ReflectionMethod($handler, 'errorInvalidException');
        $ref->setAccessible(true);
        $response = $ref->invoke($handler, $e);

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>$expected</code> in <code title=\"See " . __FILE__ . " line $line\">ErrorHandlerTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function provideInvalidReturnValue()
    {
        return [
            [
                null,
                'null',
            ],
            [
                'hello',
                'string'
            ],
            [
                42,
                '42'
            ],
            [
                1.0,
                '1.0'
            ],
            [
                false,
                'false'
            ],
            [
                [],
                'array'
            ],
            [
                (object)[],
                'stdClass'
            ],
            [
                tmpfile(),
                'resource'
            ]
        ];
    }

    /**
     * @dataProvider provideInvalidReturnValue
     * @param mixed $value
     */
    public function testErrorInvalidResponseReturnsError500($value, string $name)
    {
        $handler = new ErrorHandler();

        // $response = $handler->errorInvalidResponse($value);
        $ref = new \ReflectionMethod($handler, 'errorInvalidResponse');
        $ref->setAccessible(true);
        $response = $ref->invoke($handler, $value);

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>$name</code>.</p>\n", (string) $response->getBody());
    }

    /**
     * @dataProvider provideInvalidReturnValue
     * @param mixed $value
     */
    public function testErrorInvalidCoroutineReturnsError500($value, string $name)
    {
        $handler = new ErrorHandler();

        $file = __FILE__;
        $line = __LINE__;

        // $response = $handler->errorInvalidCoroutine($value, $file, $line);
        $ref = new \ReflectionMethod($handler, 'errorInvalidCoroutine');
        $ref->setAccessible(true);
        $response = $ref->invoke($handler, $value, $file, $line);

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to yield <code>React\Promise\PromiseInterface</code> but got <code>$name</code> near or before <code title=\"See $file line $line\">ErrorHandlerTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }
}
