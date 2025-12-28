<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\App;
use FrameworkX\ErrorHandler;
use FrameworkX\Io\RouteHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\ServerRequest;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class AppMiddlewareTest extends TestCase
{
    public function testGetMethodWithMiddlewareAddsGetRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->get('/', $middleware, $controller);
    }

    public function testHeadMethodWithMiddlewareAddsHeadRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['HEAD'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->head('/', $middleware, $controller);
    }

    public function testPostMethodWithMiddlewareAddsPostRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['POST'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->post('/', $middleware, $controller);
    }

    public function testPutMethodWithMiddlewareAddsPutRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PUT'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->put('/', $middleware, $controller);
    }

    public function testPatchMethodWithMiddlewareAddsPatchRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PATCH'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->patch('/', $middleware, $controller);
    }

    public function testDeleteMethodWithMiddlewareAddsDeleteRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['DELETE'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->delete('/', $middleware, $controller);
    }

    public function testOptionsMethodWithMiddlewareAddsOptionsRouteOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['OPTIONS'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->options('/', $middleware, $controller);
    }

    public function testAnyMethodWithMiddlewareAddsAllHttpMethodsOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->any('/', $middleware, $controller);
    }

    public function testMapMethodWithMiddlewareAddsGivenMethodsOnRouter(): void
    {
        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST'], '/', $middleware, $controller);
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->map(['GET', 'POST'], '/', $middleware, $controller);
    }

    public function testInvokeWithMiddlewareCallsNextReturnsResponseFromRouter(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            return $next($request);
        };

        $handler = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareCallsNextWithModifiedRequestReturnsResponseFromRouter(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            return $next($request->withAttribute('name', 'Alice'));
        };

        $handler = function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                $request->getAttribute('name') // @phpstan-ignore-line known to return string
            );
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareCallsNextReturnsResponseModifiedInMiddlewareFromRouter(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            $response = $next($request);
            assert($response instanceof ResponseInterface);

            return $response->withHeader('Content-Type', 'text/html');
        };

        $handler = function (ServerRequestInterface $request) {
            return new Response(
                200,
                [],
                'Alice'
            );
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareCallsNextReturnsDeferredResponseModifiedInMiddlewareFromRouter(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            $promise = $next($request);
            assert($promise instanceof PromiseInterface);
            /** @var PromiseInterface<ResponseInterface> $promise */

            return $promise->then(function (ResponseInterface $response) {
                return $response->withHeader('Content-Type', 'text/html');
            });
        };

        $handler = function (ServerRequestInterface $request) {
            return resolve(new Response(
                200,
                [],
                'Alice'
            ));
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareCallsNextReturnsCoroutineResponseModifiedInMiddlewareFromRouter(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            $generator = $next($request);
            assert($generator instanceof \Generator);

            $response = yield from $generator;
            assert($response instanceof ResponseInterface);

            return $response->withHeader('Content-Type', 'text/html');
        };

        $handler = function (ServerRequestInterface $request) {
            $name = yield resolve('Alice');
            return new Response(
                200,
                [],
                $name
            );
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareCallsNextWhichThrowsExceptionReturnsInternalServerErrorResponse(): void
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            return $next($request);
        };

        $line = __LINE__ + 2;
        $handler = function () {
            throw new \RuntimeException('Foo');
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppMiddlewareTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMiddlewareWhichThrowsExceptionReturnsInternalServerErrorResponse(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $middleware = function (ServerRequestInterface $request, callable $next) {
            throw new \RuntimeException('Foo');
        };

        $handler = function () { };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppMiddlewareTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareCallsNextReturnsResponseFromController(): void
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next) {
            return $next($request);
        });

        $app->get('/', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareInstanceCallsNextReturnsResponseFromController(): void
    {
        $middleware = new class {
            public function __invoke(ServerRequestInterface $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $app = $this->createAppWithoutLogger($middleware);

        $app->get('/', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareClassNameCallsNextReturnsResponseFromController(): void
    {
        $middleware = new class {
            public function __invoke(ServerRequestInterface $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $app = $this->createAppWithoutLogger(get_class($middleware));

        $app->get('/', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareClassNameAndSameForRouterCallsSameMiddlewareInstanceTwiceAndNextReturnsResponseFromController(): void
    {
        $middleware = new class {
            /** @var int */
            private $called = 0;
            public function __invoke(ServerRequestInterface $request, callable $next): Response
            {
                return $next($request->withAttribute('called', ++$this->called));
            }
        };

        $app = $this->createAppWithoutLogger(get_class($middleware));

        $app->get('/', get_class($middleware), function (ServerRequestInterface $request) {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                $request->getAttribute('called') . "\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("2\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareCallsNextWithModifiedRequestWillBeUsedForRouting(): void
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next): Response {
            return $next($request->withUri($request->getUri()->withPath('/users')));
        });

        $app->get('/users', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareCallsNextReturnsModifiedResponseWhenModifyingResponseFromRouter(): void
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next) {
            $response = $next($request);
            assert($response instanceof ResponseInterface);

            return $response->withHeader('Content-Type', 'text/html');
        });

        $app->get('/', function () {
            return new Response(
                200,
                [],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareReturnsResponseWithoutCallingNextReturnsResponseWithoutCallingRouter(): void
    {
        $app = $this->createAppWithoutLogger(function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $called = false;
        $app->get('/', function () use (&$called) {
            $called = true;
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());

        $this->assertFalse($called);
    }

    public function testInvokeWithGlobalMiddlewareReturnsPromiseWhichResolvesWithResponseWithoutCallingNextDoesNotCallRouter(): void
    {
        $app = $this->createAppWithoutLogger(function () {
            return resolve(new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            ));
        });

        $called = false;
        $app->get('/', function () use (&$called) {
            $called = true;
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());

        $this->assertFalse($called);
    }

    public function testInvokeWithGlobalMiddlewareReturnsResponseWhenGlobalMiddlewareModifiesAsyncResponsePromiseFromRoutedController(): void
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next) {
            return $next($request)->then(function (ResponseInterface $response) {
                return $response->withHeader('Content-Type', 'text/html');
            });
        });

        $app->get('/', function () {
            return resolve(new Response(
                200,
                [],
                "OK\n"
            ));
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithGlobalMiddlewareReturnsResponseWhenGlobalMiddlewareYieldsModifiedResponseFromAsyncGeneratorResponseFromRoutedController(): void
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next) {
            $generator = $next($request);
            assert($generator instanceof \Generator);

            $response = yield from $generator;
            assert($response instanceof ResponseInterface);

            return $response->withHeader('Content-Type', 'text/html');
        });

        $app->get('/', function () {
            $value = yield resolve("OK\n");

            return new Response(
                200,
                [],
                $value
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    /** @param callable|class-string ...$middleware */
    private function createAppWithoutLogger(...$middleware): App
    {
        return new App(
            new AccessLogHandler(DIRECTORY_SEPARATOR !== '\\' ? '/dev/null' : __DIR__ . '\\nul'),
            new ErrorHandler(),
            ...$middleware
        );
    }
}
