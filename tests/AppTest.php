<?php

namespace FrameworkX\Tests;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FrameworkX\App;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionMethod;
use ReflectionProperty;

class AppTest extends TestCase
{
    public function testGetMethodAddsGetRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->get('/', function () { });
    }

    public function testHeadMethodAddsHeadRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['HEAD'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->head('/', function () { });
    }

    public function testPostMethodAddsPostRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['POST'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->post('/', function () { });
    }

    public function testPutMethodAddsPutRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['PUT'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->put('/', function () { });
    }

    public function testPatchMethodAddsPatchRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['PATCH'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->patch('/', function () { });
    }

    public function testDeleteMethodAddsDeleteRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['DELETE'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->delete('/', function () { });
    }

    public function testOptionsMethodAddsOptionsRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['OPTIONS'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->options('/', function () { });
    }

    public function testAnyMethodAddsRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->any('/', function () { });
    }

    public function testMapMethodAddsRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET', 'POST'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->map(['GET', 'POST'], '/', function () { });
    }

    public function testRedirectMethodAddsGetRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithTargetLocation()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $handler = null;
        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $this->callback(function ($fn) use (&$handler) {
            $handler = $fn;
            return true;
        }));

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->redirect('/', '/users');

        /** @var callable $handler */
        $this->assertNotNull($handler);
        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('/users', $response->getHeaderLine('Location'));
        $this->assertEquals("See /users...\n", (string) $response->getBody());
    }

    public function testRedirectMethodWithCustomRedirectCodeAddsGetRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithCustomRedirectCode()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $handler = null;
        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $this->callback(function ($fn) use (&$handler) {
            $handler = $fn;
            return true;
        }));

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->redirect('/', '/users', 307);

        /** @var callable $handler */
        $this->assertNotNull($handler);
        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('/users', $response->getHeaderLine('Location'));
        $this->assertEquals("See /users...\n", (string) $response->getBody());
    }

    public function testRequestFromGlobalsWithNoServerVariablesDefaultsToGetRequestToLocalhost()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        // $request = $app->requestFromGlobals();
        $ref = new ReflectionMethod($app, 'requestFromGlobals');
        $ref->setAccessible(true);
        $request = $ref->invoke($app);

        /** @var ServerRequestInterface $request */
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
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
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '//';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.0';
        $_SERVER['HTTP_HOST'] = 'example.com';

        // $request = $app->requestFromGlobals();
        $ref = new ReflectionMethod($app, 'requestFromGlobals');
        $ref->setAccessible(true);
        $request = $ref->invoke($app);

        /** @var ServerRequestInterface $request */
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
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
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/path';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        // $request = $app->requestFromGlobals();
        $ref = new ReflectionMethod($app, 'requestFromGlobals');
        $ref->setAccessible(true);
        $request = $ref->invoke($app);

        /** @var ServerRequestInterface $request */
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
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
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SERVER_PROTOCOL'] = 'http/1.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';

        // $request = $app->requestFromGlobals();
        $ref = new ReflectionMethod($app, 'requestFromGlobals');
        $ref->setAccessible(true);
        $request = $ref->invoke($app);

        /** @var ServerRequestInterface $request */
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://localhost/', (string) $request->getUri());
        $this->assertEquals('1.1', $request->getProtocolVersion());
        $this->assertEquals('localhost', $request->getHeaderLine('Host'));
    }

    public function testHandleRequestWithProxyRequestReturnsResponseWithMessageThatProxyRequestAreNotAllowed()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://google.com/');
        $request = $request->withRequestTarget('http://google.com/');

        $dispatcher = $this->createMock(Dispatcher::class);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("400 (Bad Request): Proxy requests not allowed\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithFileNotFoundReturnsResponseWithFileNotFoundMessage()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/invalid');

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/invalid')->willReturn([\FastRoute\Dispatcher::NOT_FOUND]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("404 (Not Found)\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithMethodNotAllowedReturnsResponseWithMethodNotAllowedMessage()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('DELETE', 'http://localhost/users');

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('DELETE', '/users')->willReturn([\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, ['GET', 'POST']]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('GET, POST', $response->getHeaderLine('Allowed'));
        $this->assertEquals("405 (Method Not Allowed): GET, POST\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsResponseFromHandlerReturnedFromDispatcher()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsPromiseWhichFulfillsWithResponse()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            return \React\Promise\resolve(new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            ));
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

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

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPendingPromiseWhenHandlerReturnsPendingPromise()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            return new Promise(function () { });
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $resolved = false;
        $promise->then(function () use (&$resolved) {
            $resolved = true;
        }, function () use (&$resolved) {
            $resolved = true;
        });

        $this->assertFalse($resolved);
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterYieldingResolvedPromise()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            $body = yield \React\Promise\resolve("OK\n");

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                $body
            );
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

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

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterCatchingExceptionFromYieldingRejectedPromise()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            $body = '';
            try {
                yield \React\Promise\reject(new \RuntimeException("OK\n"));
            } catch (\RuntimeException $e) {
                $body = $e->getMessage();
            }

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                $body
            );
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

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

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPendingPromiseWhenHandlerReturnsCoroutineThatYieldsPendingPromise()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $handler = function () {
            yield new Promise(function () { });
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $resolved = false;
        $promise->then(function () use (&$resolved) {
            $resolved = true;
        }, function () use (&$resolved) {
            $resolved = true;
        });

        $this->assertFalse($resolved);
    }

    public function testHandleRequestWithDispatcherWithRouteFoundAndRouteVariablesReturnsResponseFromHandlerWithRouteVariablesAssignedAsRequestAttributes()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users/alice');

        $handler = function (ServerRequestInterface $request) {
            $name = $request->getAttribute('name');

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "Hello $name\n"
            );
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users/alice')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, ['name' => 'alice']]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("Hello alice\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsInternalServerErrorResponseWhenHandlerThrowsException()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 2;
        $handler = function () {
            throw new \RuntimeException('Foo');
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> (<code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>): Foo\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichRejectsWithException()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 2;
        $handler = function () {
            return \React\Promise\reject(new \RuntimeException('Foo'));
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> (<code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>): Foo\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichRejectsWithNull()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 1;
        $handler = function () {
            return \React\Promise\reject(null);
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>React\Promise\RejectedPromise</code>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsRejectedPromise()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 2;
        $handler = function () {
            yield \React\Promise\reject(new \RuntimeException('Foo'));
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> (<code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>): Foo\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichThrowsExceptionAfterYielding()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 3;
        $handler = function () {
            yield \React\Promise\resolve(null);
            throw new \RuntimeException('Foo');
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> (<code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>): Foo\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichReturnsNull()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 1;
        $handler = function () {
            $value = yield \React\Promise\resolve(null);
            return $value;
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsNull()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 1;
        $handler = function () {
            yield null;
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to yield <code>React\Promise\PromiseInterface</code> but got <code>null</code>\n", (string) $response->getBody());
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
     * @param string $name
     */
    public function testHandleRequestWithDispatcherWithRouteFoundReturnsInternalServerErrorResponseWhenHandlerReturnsWrongValue($value, $name)
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 1;
        $handler = function () use ($value) {
            return $value;
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $response = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request, $dispatcher);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>$name</code>\n", (string) $response->getBody());
    }

    /**
     * @dataProvider provideInvalidReturnValue
     * @param mixed $value
     * @param string $name
     */
    public function testHandleRequestWithDispatcherWithRouteFoundReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichFulfillsWithWrongValue($value, $name)
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $line = __LINE__ + 1;
        $handler = function () use ($value) {
            return \React\Promise\resolve($value);
        };

        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->expects($this->once())->method('dispatch')->with('GET', '/users')->willReturn([\FastRoute\Dispatcher::FOUND, $handler, []]);

        // $promise = $app->handleRequest($request, $dispatcher);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request, $dispatcher);

        /** @var PromiseInterface $promise */
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $response = null;
        $promise->then(function ($value) use (&$response) {
            $response = $value;
        });

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("500 (Internal Server Error): Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>$name</code>\n", (string) $response->getBody());
    }

    public function testLogRequestResponsePrintsRequestLogWithCurrentDateAndTime()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        // 2021-01-29 12:22:01.717 127.0.0.1 "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} 127\.0\.0\.1 \"GET \/users HTTP\/1\.1\" 200 6\n$/");

        $request = new ServerRequest('GET', 'http://localhost:8080/users', [], '', '1.1', ['REMOTE_ADDR' => '127.0.0.1']);
        $response = new Response(200, [], "Hello\n");

        // $app->logRequestResponse($request, $response);
        $ref = new ReflectionMethod($app, 'logRequestResponse');
        $ref->setAccessible(true);
        $ref->invoke($app, $request, $response);
    }

    public function testLogRequestResponseWithoutRemoteAddressPrintsRequestLogWithDashAsPlaceholder()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        // 2021-01-29 12:22:01.717 - "GET /users HTTP/1.1" 200 6\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} - \"GET \/users HTTP\/1\.1\" 200 6\n$/");

        $request = new ServerRequest('GET', 'http://localhost:8080/users');
        $response = new Response(200, [], "Hello\n");

        // $app->logRequestResponse($request, $response);
        $ref = new ReflectionMethod($app, 'logRequestResponse');
        $ref->setAccessible(true);
        $ref->invoke($app, $request, $response);
    }

    public function testLogPrintsMessageWithCurrentDateAndTime()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        // 2021-01-29 12:22:01.717 Hello\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello\n$/");

        // $app->log('Hello');
        $ref = new ReflectionMethod($app, 'log');
        $ref->setAccessible(true);
        $ref->invoke($app, 'Hello');
    }
}
