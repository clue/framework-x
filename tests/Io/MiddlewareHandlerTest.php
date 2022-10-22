<?php

namespace FrameworkX\Tests\Io;

use FrameworkX\Io\MiddlewareHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

class MiddlewareHandlerTest extends TestCase
{
    public function testOneMiddleware(): void
    {
        $handler = new MiddlewareHandler([
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            }
        ]);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testOneMiddlewareClass(): void
    {
        $middleware = new class{
            public function __invoke(ServerRequestInterface $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $handler = new MiddlewareHandler([
            $middleware,
            function (ServerRequestInterface $request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            }
        ]);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testTwoMiddleware(): void
    {
        $handler = new MiddlewareHandler([
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            }
        ]);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testThreeMiddleware(): void
    {
        $handler = new MiddlewareHandler([
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request, callable $next) {
                return $next($request);
            },
            function (ServerRequestInterface $request) {
                return new Response(
                    200,
                    [
                        'Content-Type' => 'text/html'
                    ],
                    "OK\n"
                );
            }
        ]);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }
}
