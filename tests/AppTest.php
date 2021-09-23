<?php

namespace FrameworkX\Tests;

use FrameworkX\App;
use FrameworkX\ErrorHandler;
use FrameworkX\MiddlewareHandler;
use FrameworkX\RouteHandler;
use FrameworkX\SapiHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionMethod;
use ReflectionProperty;
use function React\Promise\reject;
use function React\Promise\resolve;

class AppTest extends TestCase
{
    public function testConstructWithLoopAssignsGivenLoopInstance()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $ref = new ReflectionProperty($app, 'loop');
        $ref->setAccessible(true);
        $ret = $ref->getValue($app);

        $this->assertSame($loop, $ret);
    }

    public function testConstructWithoutLoopAssignsGlobalLoopInstance()
    {
        $app = new App();

        $ref = new ReflectionProperty($app, 'loop');
        $ref->setAccessible(true);
        $ret = $ref->getValue($app);

        $this->assertSame(Loop::get(), $ret);
    }

    public function testConstructWithLoopAndMiddlewareAssignsGivenLoopInstanceAndMiddleware()
    {
        $loop = $this->createMock(LoopInterface::class);
        $middleware = function () { };
        $app = new App($loop, $middleware);

        $ref = new ReflectionProperty($app, 'loop');
        $ref->setAccessible(true);
        $ret = $ref->getValue($app);

        $this->assertSame($loop, $ret);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[0]);
        $this->assertSame($middleware, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithInvalidLoopThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument 1 ($loop) must be callable|React\EventLoop\LoopInterface, stdClass given');
        new App((object)[]);
    }

    public function testConstructWithNullLoopButMiddlwareThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument 1 ($loop) must be callable|React\EventLoop\LoopInterface, null given');
        new App(null, function () { });
    }

    public function testRunWillRunGivenLoopInstanceAndReportListeningAddress()
    {
        $socket = @stream_socket_server('127.0.0.1:8080');
        if ($socket === false) {
            $this->markTestSkipped('Listen address :8080 already in use');
        }
        fclose($socket);

        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('run');
        $app = new App($loop);

        $this->expectOutputRegex('/' . preg_quote('Listening on http://127.0.0.1:8080' . PHP_EOL, '/') . '$/');
        $app->run();
    }

    public function testRunWillRunGivenLoopInstanceAndReportListeningAddressFromEnvironment()
    {
        $socket = @stream_socket_server('127.0.0.1:0');
        $addr = stream_socket_get_name($socket, false);
        fclose($socket);

        putenv('X_LISTEN=' . $addr);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('run');
        $app = new App($loop);

        $this->expectOutputRegex('/' . preg_quote('Listening on http://' . $addr . PHP_EOL, '/') . '$/');
        $app->run();
    }

    public function testRunWillRunGivenLoopInstanceAndReportListeningAddressFromEnvironmentWithRandomPort()
    {
        putenv('X_LISTEN=127.0.0.1:0');
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())->method('run');
        $app = new App($loop);

        $this->expectOutputRegex('/' . preg_quote('Listening on http://127.0.0.1:', '/') . '\d+' . PHP_EOL . '$/');
        $app->run();
    }

    public function testRunAppWithEmptyAddressThrowsWithoutRunningLoop()
    {
        putenv('X_LISTEN=');
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('run');
        $app = new App($loop);

        $this->expectException(\InvalidArgumentException::class);
        $app->run();
    }

    public function testRunAppWithBusyPortThrowsWithoutRunningLoop()
    {
        $socket = @stream_socket_server('127.0.0.1:0');
        $addr = stream_socket_get_name($socket, false);

        if (@stream_socket_server($addr) !== false) {
            $this->markTestSkipped('System does not prevent listening on same address twice');
        }

        putenv('X_LISTEN=' . $addr);
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())->method('run');
        $app = new App($loop);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to listen on');
        $app->run();
    }

    public function testRunOnceWillCreateRequestFromSapiThenRouteRequestThenLogRequestAndThenSendResponseFromHandler()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $response = new Response();
        $app->get('/', function () use ($response) {
            return $response;
        });

        $request = new ServerRequest('GET', 'http://example.com/');

        $sapi = $this->createMock(SapiHandler::class);
        $sapi->expects($this->once())->method('requestFromGlobals')->willReturn($request);
        $sapi->expects($this->once())->method('logRequestResponse')->with($request, $response);
        $sapi->expects($this->once())->method('sendResponse')->with($response);

        // $app->sapi = $sapi;
        $ref = new \ReflectionProperty($app, 'sapi');
        $ref->setAccessible(true);
        $ref->setValue($app, $sapi);

        // $app->runOnce();
        $ref = new \ReflectionMethod($app, 'runOnce');
        $ref->setAccessible(true);
        $ref->invoke($app);
    }

    public function testRunOnceWillCreateRequestFromSapiThenRouteRequestThenLogRequestAndThenSendResponseFromDeferredHandler()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $response = new Response();
        $app->get('/', function () use ($response) {
            return resolve($response);
        });

        $request = new ServerRequest('GET', 'http://example.com/');

        $sapi = $this->createMock(SapiHandler::class);
        $sapi->expects($this->once())->method('requestFromGlobals')->willReturn($request);
        $sapi->expects($this->once())->method('logRequestResponse')->with($request, $response);
        $sapi->expects($this->once())->method('sendResponse')->with($response);

        // $app->sapi = $sapi;
        $ref = new \ReflectionProperty($app, 'sapi');
        $ref->setAccessible(true);
        $ref->setValue($app, $sapi);

        // $app->runOnce();
        $ref = new \ReflectionMethod($app, 'runOnce');
        $ref->setAccessible(true);
        $ref->invoke($app);
    }

    public function testGetMethodAddsGetRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->get('/', function () { });
    }

    public function testHeadMethodAddsHeadRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['HEAD'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->head('/', function () { });
    }

    public function testPostMethodAddsPostRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['POST'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->post('/', function () { });
    }

    public function testPutMethodAddsPutRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PUT'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->put('/', function () { });
    }

    public function testPatchMethodAddsPatchRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PATCH'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->patch('/', function () { });
    }

    public function testDeleteMethodAddsDeleteRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['DELETE'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->delete('/', function () { });
    }

    public function testOptionsMethodAddsOptionsRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['OPTIONS'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->options('/', function () { });
    }

    public function testAnyMethodAddsRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->any('/', function () { });
    }

    public function testMapMethodAddsRouteOnRouter()
    {
        $app = new App();

        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST'], '/', $this->anything());

        $ref = new ReflectionProperty($app, 'router');
        $ref->setAccessible(true);
        $ref->setValue($app, $router);

        $app->map(['GET', 'POST'], '/', function () { });
    }

    public function testRedirectMethodAddsAnyRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithTargetLocation()
    {
        $app = new App();

        $handler = null;
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->callback(function ($fn) use (&$handler) {
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
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/users', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to /users</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"/users\"><code>/users</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testRedirectMethodWithCustomRedirectCodeAddsAnyRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithCustomRedirectCode()
    {
        $app = new App();

        $handler = null;
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->callback(function ($fn) use (&$handler) {
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
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(307, $response->getStatusCode());
        $this->assertEquals('/users', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to /users</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"/users\"><code>/users</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithProxyRequestReturnsResponseWithMessageThatProxyRequestAreNotAllowed()
    {
        $app = new App();

        $request = new ServerRequest('GET', 'http://google.com/');
        $request = $request->withRequestTarget('http://google.com/');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 400: Proxy Requests Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check your settings and retry.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithUnknownRouteReturnsResponseWithFileNotFoundMessage()
    {
        $app = new App();

        $request = new ServerRequest('GET', 'http://localhost/invalid');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithInvalidRequestMethodReturnsResponseWithSingleMethodNotAllowedMessage()
    {
        $app = new App();

        $app->get('/users', function () { });

        $request = new ServerRequest('POST', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code> request.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithInvalidRequestMethodReturnsResponseWithMultipleMethodNotAllowedMessage()
    {
        $app = new App();

        $app->get('/users', function () { });
        $app->head('/users', function () { });
        $app->post('/users', function () { });

        $request = new ServerRequest('DELETE', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET, HEAD, POST', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code>/<code>HEAD</code>/<code>POST</code> request.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsResponseFromMatchingRouteHandler()
    {
        $app = new App();

        $app->get('/users', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsPromiseWhichFulfillsWithResponse()
    {
        $app = new App();

        $app->get('/users', function () {
            return resolve(new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            ));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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

    public function testHandleRequestWithMatchingRouteReturnsPendingPromiseWhenHandlerReturnsPendingPromise()
    {
        $app = new App();

        $app->get('/users', function () {
            return new Promise(function () { });
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

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

    public function testHandleRequestWithMatchingRouteReturnsResponseWhenHandlerReturnsCoroutineWhichReturnsResponseWithoutYielding()
    {
        $app = new App();

        $app->get('/users', function () {
            if (false) {
                yield;
            }

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterYieldingResolvedPromise()
    {
        $app = new App();

        $app->get('/users', function () {
            $body = yield resolve("OK\n");

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                $body
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterCatchingExceptionFromYieldingRejectedPromise()
    {
        $app = new App();

        $app->get('/users', function () {
            $body = '';
            try {
                yield reject(new \RuntimeException("OK\n"));
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
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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

    public function testHandleRequestWithMatchingRouteReturnsPendingPromiseWhenHandlerReturnsCoroutineThatYieldsPendingPromise()
    {
        $app = new App();

        $app->get('/users', function () {
            yield new Promise(function () { });
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $promise = $ref->invoke($app, $request);

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

    public function testHandleRequestWithMatchingRouteAndRouteVariablesReturnsResponseFromHandlerWithRouteVariablesAssignedAsRequestAttributes()
    {
        $app = new App();

        $app->get('/users/{name}', function (ServerRequestInterface $request) {
            $name = $request->getAttribute('name');

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "Hello $name\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/users/alice');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("Hello alice\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerThrowsException()
    {
        $app = new App();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichRejectsWithException()
    {
        $app = new App();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            return reject(new \RuntimeException('Foo'));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichRejectsWithNull()
    {
        $app = new App();

        $app->get('/users', function () {
            return reject(null);
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>React\Promise\RejectedPromise</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsRejectedPromise()
    {
        $app = new App();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            yield reject(new \RuntimeException('Foo'));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichThrowsExceptionWithoutYielding()
    {
        $app = new App();

        $line = __LINE__ + 5;
        $app->get('/users', function () {
            if (false) {
                yield;
            }
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichThrowsExceptionAfterYielding()
    {
        $app = new App();

        $line = __LINE__ + 3;
        $app->get('/users', function () {
            yield resolve(null);
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichReturnsNull()
    {
        $app = new App();

        $app->get('/users', function () {
            $value = yield resolve(null);
            return $value;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsNullImmediately()
    {
        $app = new App();

        $line = __LINE__ + 3;
        $app->get('/users', function () {
            yield null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to yield <code>React\Promise\PromiseInterface</code> but got <code>null</code> near or before <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsWrongValue()
    {
        $app = new App();

        $app->get('/users', function () {
            return null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $response = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
        $ref->setAccessible(true);
        $response = $ref->invoke($app, $request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichFulfillsWithWrongValue()
    {
        $app = new App();

        $app->get('/users', function () {
            return resolve(null);
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsWrongValueAfterYielding()
    {
        $app = new App();

        $app->get('/users', function () {
            yield resolve(true);
            return null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        // $promise = $app->handleRequest($request);
        $ref = new ReflectionMethod($app, 'handleRequest');
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
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }
}
