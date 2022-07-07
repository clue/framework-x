<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\App;
use FrameworkX\Io\FiberHandler;
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
    public function testGetMethodWithMiddlewareAddsGetRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->get('/', $middleware, $controller);
    }

    public function testHeadMethodWithMiddlewareAddsHeadRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['HEAD'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->head('/', $middleware, $controller);
    }

    public function testPostMethodWithMiddlewareAddsPostRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['POST'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->post('/', $middleware, $controller);
    }

    public function testPutMethodWithMiddlewareAddsPutRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PUT'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->put('/', $middleware, $controller);
    }

    public function testPatchMethodWithMiddlewareAddsPatchRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PATCH'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->patch('/', $middleware, $controller);
    }

    public function testDeleteMethodWithMiddlewareAddsDeleteRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['DELETE'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->delete('/', $middleware, $controller);
    }

    public function testOptionsMethodWithMiddlewareAddsOptionsRouteOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['OPTIONS'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->options('/', $middleware, $controller);
    }

    public function testAnyMethodWithMiddlewareAddsAllHttpMethodsOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->any('/', $middleware, $controller);
    }

    public function testMapMethodWithMiddlewareAddsGivenMethodsOnRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function () {};
        $controller = function () { };

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST'], '/', $middleware, $controller);

        $ref = new \ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->map(['GET', 'POST'], '/', $middleware, $controller);
    }

    public function testMiddlewareCallsNextReturnsResponseFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testMiddlewareCallsNextWithModifiedRequestReturnsResponseFromRouter()
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
                $request->getAttribute('name')
            );
        };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testMiddlewareCallsNextReturnsResponseModifiedInMiddlewareFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testMiddlewareCallsNextReturnsDeferredResponseModifiedInMiddlewareFromRouter()
    {
        $app = $this->createAppWithoutLogger();

        $middleware = function (ServerRequestInterface $request, callable $next) {
            $promise = $next($request);
            assert($promise instanceof PromiseInterface);

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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testMiddlewareCallsNextReturnsCoroutineResponseModifiedInMiddlewareFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('Alice', (string) $response->getBody());
    }

    public function testMiddlewareCallsNextWhichThrowsExceptionReturnsInternalServerErrorResponse()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppMiddlewareTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testMiddlewareWhichThrowsExceptionReturnsInternalServerErrorResponse()
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $middleware = function (ServerRequestInterface $request, callable $next) {
            throw new \RuntimeException('Foo');
        };

        $handler = function () { };

        $app->get('/', $middleware, $handler);

        $request = new ServerRequest('GET', 'http://localhost/');

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppMiddlewareTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareCallsNextReturnsResponseFromController()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareInstanceCallsNextReturnsResponseFromController()
    {
        $middleware = new class {
            public function __invoke(ServerRequestInterface $request, callable $next)
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareClassNameCallsNextReturnsResponseFromController()
    {
        $middleware = new class {
            public function __invoke(ServerRequestInterface $request, callable $next)
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareClassNameAndSameForRouterCallsSameMiddlewareInstanceTwiceAndNextReturnsResponseFromController()
    {
        $middleware = new class {
            private $called = 0;
            public function __invoke(ServerRequestInterface $request, callable $next)
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("2\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareCallsNextWithModifiedRequestWillBeUsedForRouting()
    {
        $app = $this->createAppWithoutLogger(function (ServerRequestInterface $request, callable $next) {
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareCallsNextReturnsModifiedResponseWhenModifyingResponseFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareReturnsResponseWithoutCallingNextReturnsResponseWithoutCallingRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());

        $this->assertFalse($called);
    }

    public function testGlobalMiddlewareReturnsPromiseWhichResolvesWithResponseWithoutCallingNextDoesNotCallRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());

        $this->assertFalse($called);
    }

    public function testGlobalMiddlewareCallsNextReturnsPromiseWhichResolvesWithModifiedResponseWhenModifyingPromiseWhichResolvesToResponseFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testGlobalMiddlewareCallsNextReturnsPromiseWhichResolvesWithModifiedResponseWhenModifyingCoroutineWhichYieldsResponseFromRouter()
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

        // $response = $app->handleRequest($request);
        $ref = new \ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    /** @param callable|class-string ...$middleware */
    private function createAppWithoutLogger(...$middleware): App
    {
        $app = new App(...$middleware);

        $ref = new \ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $middleware = $ref->getValue($app);

        $ref = new \ReflectionProperty($middleware, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($middleware);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);

            $next = array_shift($handlers);
            $this->assertInstanceOf(AccessLogHandler::class, $next);

            array_unshift($handlers, $next, $first);
        }

        $first = array_shift($handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $first);

        $ref->setValue($middleware, $handlers);

        return $app;
    }
}
