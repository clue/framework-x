<?php

namespace FrameworkX\Tests\Io;

use FrameworkX\Io\MiddlewareHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Async\await;

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

    public function testPsrMiddleware(): void
    {
        $handler = new MiddlewareHandler([
            function (ServerRequestInterface $request, callable $next) {
                $response = $next($request);
                $decorate = static function (ResponseInterface $response) {
                    return $response->withAddedHeader('X-Middleware', '1');
                };
                if ($response instanceof PromiseInterface) {
                    return $response->then(function ($response) use ($decorate) {
                        assert($response instanceof ResponseInterface);
                        return $decorate($response);
                    });
                }
                if ($response instanceof \Generator) {
                    return (function () use ($response, $decorate) {
                        return $decorate(yield from $response);
                    })();
                }
                return $decorate($response);
            },
            new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request)->withAddedHeader('X-Middleware', '2');
                }
            },
            function (ServerRequestInterface $request, callable $next) {
                $response = $next($request);
                assert($response instanceof ResponseInterface);
                $deferred = new Deferred();
                $deferred->resolve($response->withAddedHeader('X-Middleware', '3'));
                return $deferred->promise();
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

        $response = await($handler($request));

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals([3, 2, 1], $response->getHeader('X-Middleware'));
    }
}
