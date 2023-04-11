<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\Io\LogStreamHandler;
use PHPUnit\Framework\TestCase;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Stream\ThroughStream;
use function React\Promise\resolve;

class AccessLogHandlerTest extends TestCase
{
    public function testInvokeLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeLogsRequestWithEncodedQueryParameters(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/?a=1&b=hello wörld', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /?a=1&b=hello%20w%C3%B6rld HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /\?a=1&b=hello%20w%C3%B6rld HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeLogsRequestWithEscapedSpecialCharactersInRequestMethodAndTarget(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GE"T', 'http://localhost:8080/wörld', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('/wörld');
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GE\x22T /w\xC3\xB6rld HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GE\\\\x22T /w\\\\xC3\\\\xB6rld HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeLogsRequestForHeadRequestWithResponseSizeAsZero(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('HEAD', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "HEAD\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "HEAD /users HTTP/1.1" 200 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "HEAD /users HTTP/1\.1" 200 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeLogsRequestForNoContentResponseWithResponseSizeAsZero(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(204, [], "No Content\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 204 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 204 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeLogsRequestForNotModifiedResponseWithResponseSizeAsZero(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(304, [], "Not Modified\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 304 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 304 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithPlainProxyRequestLogsRequestWithRequestTargetInOriginForm(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('http://localhost:8080/users');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET http://localhost:8080/users HTTP/1.1" 400 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET http://localhost:8080/users HTTP/1\.1" 400 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithConnectProxyRequestLogsRequestWithRequestTargetInAuthorityForm(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('CONNECT', 'example.com:8080', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('example.com:8080');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "CONNECT example.com:8080 HTTP/1.1" 400 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "CONNECT example.com:8080 HTTP/1\.1" 400 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithOptionsAsteriskRequestLogsRequestWithRequestTargetAsteriskForm(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('OPTIONS', 'http://example.com:8080', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withRequestTarget('*');
        $response = new Response(400, [], "");

        // 2021-01-29 12:22:01.717 127.0.0.1 "OPTIONS * HTTP/1.1" 400 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "OPTIONS \* HTTP/1\.1" 400 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithDeferredNextLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return resolve($response); });
    }

    public function testInvokeWithCoroutineNextLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        $generator = $handler($request, function () use ($response) {
            if (false) { // @phpstan-ignore-line
                yield;
            }
            return $response;
        });

        /** @var \Generator $generator */
        $this->assertInstanceOf(\Generator::class, $generator);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $generator->next();
    }

    public function testInvokeWithStreamingResponseLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $stream = new ThroughStream();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 10 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 10 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
        $stream->write('hello');
        $stream->end('world');
    }

    public function testInvokeWithStreamingResponseThatClosesAfterSomeTimeLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $stream = new ThroughStream();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 0 0.100\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 0 0\.1\d\d$#'));
        $handler($request, function () use ($response) { return $response; });

        usleep(150000); // 100ms + 50ms to account for inaccurate clocks
        $stream->end();
    }

    public function testInvokeWithClosedStreamingResponseLogsRequest(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $stream = new ThroughStream();
        $stream->close();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 0 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^127\.0\.0\.1 "GET /users HTTP/1\.1" 200 0 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithStreamingResponsePrintsNothingIfStreamIsPending(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $stream = new ThroughStream();
        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], $stream);

        $this->expectOutputString('');
        $handler($request, function () use ($response) { return $response; });
        $stream->write('hello');
    }

    public function testInvokeWithRemoteAddrAttributeLogsRequestWithIpFromAttribute(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withAttribute('remote_addr', '10.0.0.1');
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 10.0.0.1 "GET /users HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^10\.0\.0\.1 "GET /users HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }

    public function testInvokeWithoutRemoteAddressLogsRequestWithDashAsPlaceholder(): void
    {
        $handler = new AccessLogHandler();

        $logger = $this->createMock(LogStreamHandler::class);
        // $handler->logger = $logger;
        $ref = new \ReflectionProperty($handler, 'logger');
        $ref->setAccessible(true);
        $ref->setValue($handler, $logger);

        $request = new ServerRequest('GET', 'http://localhost:8080/users');
        $response = new Response(200, [], "Hello\n");

        // 2021-01-29 12:22:01.717 - "GET /users HTTP/1.1" 200 6 0.000\n
        $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('#^- "GET /users HTTP/1\.1" 200 6 0\.0\d\d$#'));
        $handler($request, function () use ($response) { return $response; });
    }
}
