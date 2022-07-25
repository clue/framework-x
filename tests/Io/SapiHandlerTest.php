<?php

namespace FrameworkX\Tests\Io;

use FrameworkX\Io\SapiHandler;
use PHPUnit\Framework\TestCase;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

class SapiHandlerTest extends TestCase
{
    public function testRequestFromGlobalsWithNoServerVariablesDefaultsToGetRequestToLocalhost()
    {
        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://localhost/', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithHeadRequest()
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '//';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.0';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('HEAD', $request->getMethod());
        $this->assertEquals('http://example.com//', (string) $request->getUri());
        $this->assertEquals('1.0', $request->getProtocolVersion());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithGetRequestOverCustomPort()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/path';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://localhost:8080/path', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost:8080', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithGetRequestOverHttps()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://localhost/', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithOptionsAsterisk()
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '*';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('OPTIONS', $request->getMethod());
        $this->assertEquals('http://localhost', (string) $request->getUri());
        $this->assertEquals('*', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithGetProxy()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = 'http://example.com/';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://example.com/', (string) $request->getUri());
        $this->assertEquals('http://example.com/', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithConnectProxy()
    {
        $_SERVER['REQUEST_METHOD'] = 'CONNECT';
        $_SERVER['REQUEST_URI'] = 'example.com:443';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com:443';

        $sapi = new SapiHandler();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('example.com:443', (string) $request->getUri());
        $this->assertEquals('example.com:443', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('example.com:443', $request->getHeaderLine('Host'));
    }

    public function testSendResponseSendsEmptyResponseWithNoHeadersAndEmptyBodyAndAssignsNoContentTypeAndEmptyContentLength()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(200, [], '');

        $this->expectOutputString('');
        $sapi->sendResponse($response);
        $this->assertEquals(['Content-Type:', 'Content-Length: 0'], xdebug_get_headers());
    }

    public function testSendResponseSendsJsonResponseWithGivenHeadersAndBodyAndAssignsMatchingContentLength()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('{}');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json', 'Content-Length: 2']), xdebug_get_headers());
    }

    /**
     * @backupGlobals enabled
     */
    public function testSendResponseSendsJsonResponseWithGivenHeadersAndMatchingContentLengthButEmptyBodyForHeadRequest()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json', 'Content-Length: 2']), xdebug_get_headers());
    }

    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndAssignsNoContentLengthForNoContentResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(204, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json']), xdebug_get_headers());
    }

    public function testSendResponseSendsEmptyBodyWithGivenHeadersButWithoutExplicitContentLengthForNoContentResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(204, ['Content-Type' => 'application/json', 'Content-Length' => 2], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json']), xdebug_get_headers());
    }

    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndAssignsContentLengthForNotModifiedResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(304, ['Content-Type' => 'application/json'], 'null');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json', 'Content-Length: 4']), xdebug_get_headers());
    }

    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndExplicitContentLengthForNotModifiedResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $response = new Response(304, ['Content-Type' => 'application/json', 'Content-Length' => '2'], '');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type: application/json', 'Content-Length: 2']), xdebug_get_headers());
    }

    public function testSendResponseSendsStreamingResponseWithNoHeadersAndBodyFromStreamData()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('test');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2'];
        $this->assertEquals(array_merge($previous, ['Content-Type:']), xdebug_get_headers());

        $body->end('test');
    }

    /**
     * @backupGlobals enabled
     */
    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForHeadRequest()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2', 'Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type:']), xdebug_get_headers());
        $this->assertFalse($body->isReadable());
    }

    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForNotModifiedResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $body = new ThroughStream();
        $response = new Response(304, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2', 'Content-Type:', 'Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type:']), xdebug_get_headers());
        $this->assertFalse($body->isReadable());
    }

    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForNoContentResponse()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiHandler();
        $body = new ThroughStream();
        $response = new Response(204, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2', 'Content-Type:', 'Content-Type:', 'Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type:']), xdebug_get_headers());
        $this->assertFalse($body->isReadable());
    }

    public function testSendResponseSendsStreamingResponseWithNoHeadersAndBodyFromStreamDataAndNoBufferHeaderForNginxServer()
    {
        if (headers_sent() || !function_exists('xdebug_get_headers')) {
            $this->markTestSkipped('Test requires running phpunit with --stderr and Xdebug enabled');
        }

        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1';
        $sapi = new SapiHandler();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('test');
        $sapi->sendResponse($response);

        $previous = ['Content-Type:', 'Content-Length: 2', 'Content-Type:', 'Content-Type:', 'Content-Type:', 'Content-Type:'];
        $this->assertEquals(array_merge($previous, ['Content-Type:', 'X-Accel-Buffering: no']), xdebug_get_headers());

        $body->end('test');
    }

    public function testLogPrintsMessageWithCurrentDateAndTime()
    {
        // 2021-01-29 12:22:01.717 Hello\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/");

        $sapi = new SapiHandler();
        $sapi->log('Hello');
    }
}
