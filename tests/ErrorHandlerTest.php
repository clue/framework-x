<?php

namespace FrameworkX\Tests;

use FrameworkX\ErrorHandler;
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
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
                'new\n line'
            ],
            [
                'sla/she\'s\\n',
                'sla/she\'s\\\\n'
            ],
            [
                "hello\r\nworld",
                'hello\r\nworld'
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
                utf8_decode("hellö!"),
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
        $response = $handler->errorInvalidException($e);

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

        $response = $handler->errorInvalidResponse($value);

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

        $response = $handler->errorInvalidCoroutine($value);

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to yield <code>React\Promise\PromiseInterface</code> but got <code>$name</code>.</p>\n", (string) $response->getBody());
    }
}
