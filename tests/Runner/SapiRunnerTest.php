<?php

namespace FrameworkX\Tests\Runner;

use FrameworkX\Runner\SapiRunner;
use PHPUnit\Framework\TestCase;
use React\Http\Message\Response;
use React\Stream\ThroughStream;
use function React\Promise\resolve;

class SapiRunnerTest extends TestCase
{
    public function testRequestFromGlobalsWithNoServerVariablesDefaultsToGetRequestToLocalhost(): void
    {
        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://localhost/', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithHeadRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '//';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.0';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('HEAD', $request->getMethod());
        $this->assertEquals('http://example.com//', (string) $request->getUri());
        $this->assertEquals('1.0', $request->getProtocolVersion());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithGetRequestOverCustomPort(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/path';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://localhost:8080/path', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost:8080', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithGetRequestOverHttps(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://localhost/', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithOptionsAsterisk(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '*';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $sapi = new SapiRunner();
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
    public function testRequestFromGlobalsWithGetProxy(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = 'http://example.com/';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $sapi = new SapiRunner();
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
    public function testRequestFromGlobalsWithConnectProxy(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'CONNECT';
        $_SERVER['REQUEST_URI'] = 'example.com:443';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com:443';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('http://example.com:443', (string) $request->getUri());
        $this->assertEquals('example.com:443', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('example.com:443', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithConnectProxyWithDefaultHttpPort(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'CONNECT';
        $_SERVER['REQUEST_URI'] = 'example.com:80';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('http://example.com', (string) $request->getUri());
        $this->assertEquals('example.com:80', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('example.com', $request->getHeaderLine('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithConnectProxyWithoutHostHeader(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'CONNECT';
        $_SERVER['REQUEST_URI'] = 'example.com:8080';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('http://example.com:8080', (string) $request->getUri());
        $this->assertEquals('example.com:8080', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertFalse($request->hasHeader('Host'));
    }

    /**
     * @backupGlobals enabled
     */
    public function testRequestFromGlobalsWithConnectProxyOverHttps(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'CONNECT';
        $_SERVER['REQUEST_URI'] = 'example.com:443';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'example.com:443';
        $_SERVER['HTTPS'] = 'on';

        $sapi = new SapiRunner();
        $request = $sapi->requestFromGlobals();

        $this->assertEquals('CONNECT', $request->getMethod());
        $this->assertEquals('https://example.com', (string) $request->getUri());
        $this->assertEquals('example.com:443', $request->getRequestTarget());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('example.com:443', $request->getHeaderLine('Host'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsEmptyResponseWithNoHeadersAndEmptyBodyAndAssignsNoContentTypeAndEmptyContentLength(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(200, [], '');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:', 'Content-Length: 0'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsJsonResponseWithGivenHeadersAndBodyAndAssignsMatchingContentLength(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('{}');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json', 'Content-Length: 2'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsJsonResponseWithGivenHeadersAndMatchingContentLengthButEmptyBodyForHeadRequest(): void
    {
        header_remove();
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(200, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json', 'Content-Length: 2'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndAssignsNoContentLengthForNoContentResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(204, ['Content-Type' => 'application/json'], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsEmptyBodyWithGivenHeadersButWithoutExplicitContentLengthForNoContentResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(204, ['Content-Type' => 'application/json', 'Content-Length' => '2'], '{}');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndAssignsContentLengthForNotModifiedResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(304, ['Content-Type' => 'application/json'], 'null');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json', 'Content-Length: 4'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsEmptyBodyWithGivenHeadersAndExplicitContentLengthForNotModifiedResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(304, ['Content-Type' => 'application/json', 'Content-Length' => '2'], '');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type: application/json', 'Content-Length: 2'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsStreamingResponseWithNoHeadersAndBodyFromStreamData(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('test');
        $sapi->sendResponse($response);

        $body->end('test');

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForHeadRequest(): void
    {
        header_remove();
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $this->assertFalse($body->isReadable());

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForNotModifiedResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $body = new ThroughStream();
        $response = new Response(304, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $this->assertFalse($body->isReadable());

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseClosesStreamingResponseAndSendsResponseWithNoHeadersAndBodyForNoContentResponse(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $body = new ThroughStream();
        $response = new Response(204, [], $body);

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        $this->assertFalse($body->isReadable());

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSendsStreamingResponseWithNoHeadersAndBodyFromStreamDataAndNoBufferHeaderForNginxServer(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1';
        $sapi = new SapiRunner();
        $body = new ThroughStream();
        $response = new Response(200, [], $body);

        $this->expectOutputString('test');
        $sapi->sendResponse($response);

        $body->end('test');

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:', 'X-Accel-Buffering: no'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testSendResponseSetsMultipleCookieHeaders(): void
    {
        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $sapi = new SapiRunner();
        $response = new Response(204, ['Set-Cookie' => ['1=1', '2=2']], '');

        $this->expectOutputString('');
        $sapi->sendResponse($response);

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:', 'Set-Cookie: 1=1', 'Set-Cookie: 2=2'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testInvokeWillSendResponseHeadersFromHandler(): void
    {
        $sapi = new SapiRunner();

        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';

        $this->expectOutputString('');
        $sapi(function () {
            return new Response();
        });

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:', 'Content-Length: 0'], xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testInvokeWillSendResponseHeadersFromDeferredHandler(): void
    {
        $sapi = new SapiRunner();

        header_remove();
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';

        $this->expectOutputString('');
        $sapi(function () {
            return resolve(new Response());
        });

        if (!function_exists('xdebug_get_headers')) {
            // $this->markTestIncomplete('Testing headers requires running PHPUnit with Xdebug enabled');
            return;
        }
        $this->assertEquals(['Content-Type:', 'Content-Length: 0'], xdebug_get_headers());
    }
}
