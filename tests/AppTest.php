<?php

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Frugal\App;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

class AppTest extends TestCase
{
    public function testGetMethodAddsGetRouteOnRouter()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('get')->with('/', $this->anything());

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
        $router->expects($this->once())->method('head')->with('/', $this->anything());

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
        $router->expects($this->once())->method('post')->with('/', $this->anything());

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
        $router->expects($this->once())->method('put')->with('/', $this->anything());

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
        $router->expects($this->once())->method('patch')->with('/', $this->anything());

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
        $router->expects($this->once())->method('delete')->with('/', $this->anything());

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
        $router->expects($this->once())->method('get')->with('/', $this->callback(function ($fn) use (&$handler) {
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
        $router->expects($this->once())->method('get')->with('/', $this->callback(function ($fn) use (&$handler) {
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
