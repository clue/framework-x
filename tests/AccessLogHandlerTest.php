<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use PHPUnit\Framework\TestCase;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Stream\ThroughStream;
use function React\Promise\resolve;

class AccessLogHandlerTest extends TestCase
{
    public function testInvokePrintsRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokePrintsRequestWithQueryParametersLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/?a=1&b=hello wörld', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /?a=1&b=hello%20w%C3%B6rld HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/\?a=1&b=hello%20w%C3%B6rld HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokePrintsRequestWithEscapedSpecialCharactersInRequestMethodAndTargetWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GE"T', 'http://localhost:8080/wörld', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('/wörld');
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GE\x22T /w\xC3\xB6rld HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GE\\\\x22T \/w\\\\xC3\\\\xB6rld HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokePrintsPlainProxyRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('http://localhost:8080/users');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET http://localhost:8080/users HTTP/1.1" 400 0\n
        $this->expectOutputRegex("#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET http://localhost:8080/users HTTP/1\.1\" 400 0" . PHP_EOL . "$#");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokePrintsConnectProxyRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('CONNECT', 'example.com:8080', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('example.com:8080');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "CONNECT example.com:8080 HTTP/1.1" 400 0\n
        $this->expectOutputRegex("#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"CONNECT example.com:8080 HTTP/1\.1\" 400 0" . PHP_EOL . "$#");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokePrintsOptionsAsteriskLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('OPTIONS', 'http://example.com:8080', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('*');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "OPTIONS * HTTP/1.1" 400 0\n
        $this->expectOutputRegex("#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"OPTIONS \* HTTP/1\.1\" 400 0" . PHP_EOL . "$#");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithDeferredNextPrintsRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return resolve($response); });
    }

    public function testInvokeWithCoroutineNextPrintsRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        $generator = $handler($request, function () use ($response) {
            if (false) {
                yield;
            }
            return $response;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $generator->next();
    }

    public function testInvokeWithStreamingResponsePrintsRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $stream = new ThroughStream();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 10\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 10" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
        $stream->write('hello');
        $stream->end('world');
    }

    public function testInvokeWithClosedStreamingResponsePrintsRequestLogWithCurrentDateAndTime()
    {
        $handler = new AccessLogHandler();

        $stream = new ThroughStream();
        $stream->close();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 10\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 0" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithStreamingResponsePrintsNothingIfStreamIsPending()
    {
        $handler = new AccessLogHandler();

        $stream = new ThroughStream();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        $this->expectOutputString('');
        $handler($request, function () use ($response) { return $response; });
        $stream->write('hello');
    }

    public function testInvokeWithoutRemoteAddressPrintsRequestLogWithDashAsPlaceholder()
    {
        $handler = new AccessLogHandler();

        $request = new ServerRequest('GET', 'http://localhost:8080/users');
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 - "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} - \"GET \/users HTTP\/1\.1\" 200 6" . PHP_EOL . "$/");
        $handler($request, function () use ($response) { return $response; });
    }
}
