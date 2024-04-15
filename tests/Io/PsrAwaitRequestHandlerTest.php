<?php

namespace FrameworkX\Tests\Io;

use FrameworkX\Io\PsrAwaitRequestHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;

class PsrAwaitRequestHandlerTest extends TestCase
{

    public function testHandleReturnsResolvedResponseFromNextHandler(): void
    {
        $response = new Response(200, [], '');
        $deferred = new Deferred();
        $deferred->resolve($response);
        $handler = new PsrAwaitRequestHandler(function () use ($deferred) {
            return $deferred->promise();
        });

        $request = new ServerRequest('GET', 'http://localhost/');
        $this->assertSame($response, $handler->handle($request));
    }

    public function testHandleWithoutNextReturnsEmptyResponse(): void
    {
        $handler = new PsrAwaitRequestHandler();

        $request = new ServerRequest('GET', 'http://localhost/');
        $response = $handler->handle($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($response->getBody()->getContents());
    }
}
