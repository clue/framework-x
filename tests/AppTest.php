<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\App;
use FrameworkX\Container;
use FrameworkX\ErrorHandler;
use FrameworkX\Io\FiberHandler;
use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\Io\RouteHandler;
use FrameworkX\Io\SapiHandler;
use FrameworkX\Tests\Fixtures\InvalidAbstract;
use FrameworkX\Tests\Fixtures\InvalidConstructorInt;
use FrameworkX\Tests\Fixtures\InvalidConstructorIntersection;
use FrameworkX\Tests\Fixtures\InvalidConstructorPrivate;
use FrameworkX\Tests\Fixtures\InvalidConstructorProtected;
use FrameworkX\Tests\Fixtures\InvalidConstructorSelf;
use FrameworkX\Tests\Fixtures\InvalidConstructorUnion;
use FrameworkX\Tests\Fixtures\InvalidConstructorUnknown;
use FrameworkX\Tests\Fixtures\InvalidConstructorUntyped;
use FrameworkX\Tests\Fixtures\InvalidInterface;
use FrameworkX\Tests\Fixtures\InvalidTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionMethod;
use ReflectionProperty;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class AppTest extends TestCase
{
    public function testConstructWithMiddlewareAssignsGivenMiddleware()
    {
        $middleware = function () { };
        $app = new App($middleware);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(4, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[3]);
    }

    public function testConstructWithContainerAssignsDefaultHandlersAndContainerForRouteHandlerOnly()
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getAccessLogHandler')->willReturn($accessLogHandler);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($container);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);

        $routeHandler = $handlers[2];
        $ref = new ReflectionProperty($routeHandler, 'container');
        $ref->setAccessible(true);
        $this->assertSame($container, $ref->getValue($routeHandler));
    }

    public function testConstructWithContainerAndMiddlewareClassNameAssignsCallableFromContainerAsMiddleware()
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('callable')->with('stdClass')->willReturn($middleware);

        $app = new App($container, \stdClass::class);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(4, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[3]);

        $routeHandler = $handlers[3];
        $ref = new ReflectionProperty($routeHandler, 'container');
        $ref->setAccessible(true);
        $this->assertSame($container, $ref->getValue($routeHandler));
    }

    public function testConstructWithErrorHandlerOnlyAssignsErrorHandlerAfterDefaultAccessLogHandler()
    {
        $errorHandler = new ErrorHandler();

        $app = new App($errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithErrorHandlerClassOnlyAssignsErrorHandlerAfterDefaultAccessLogHandler()
    {
        $app = new App(ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndErrorHandlerAssignsErrorHandlerAfterDefaultAccessLogHandler()
    {
        $errorHandler = new ErrorHandler();

        $app = new App(new Container(), $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndErrorHandlerClassAssignsErrorHandlerFromContainerAfterDefaultAccessLogHandler()
    {
        $errorHandler = new ErrorHandler();

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($container, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithMultipleContainersAndErrorHandlerClassAssignsErrorHandlerFromLastContainerBeforeErrorHandlerAfterDefaultAccessLogHandler()
    {
        $errorHandler = new ErrorHandler();

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getErrorHandler');

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($unused, $container, ErrorHandler::class, $unused);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithMultipleContainersAndMiddlewareAssignsErrorHandlerFromLastContainerBeforeMiddlewareAfterDefaultAccessLogHandler()
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };
        $errorHandler = new ErrorHandler();

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getErrorHandler');

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($unused, $container, $middleware, $unused);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(4, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[3]);
    }

    public function testConstructWithMiddlewareAndErrorHandlerAssignsGivenErrorHandlerAfterMiddlewareAndDefaultAccessLogHandlerAndErrorHandlerFirst()
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };
        $errorHandler = new ErrorHandler();

        $app = new App($middleware, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(5, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertNotSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($errorHandler, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithMultipleContainersAndMiddlewareAndErrorHandlerClassAssignsDefaultErrorHandlerFromLastContainerBeforeMiddlewareAndErrorHandlerFromLastContainerAfterDefaultAccessLogHandler()
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getErrorHandler');

        $errorHandler1 = new ErrorHandler();
        $container1 = $this->createMock(Container::class);
        $container1->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler1);

        $errorHandler2 = new ErrorHandler();
        $container2 = $this->createMock(Container::class);
        $container2->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler2);

        $app = new App($unused, $container1, $middleware, $container2, ErrorHandler::class, $unused);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(5, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler1, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($errorHandler2, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithAccessLogHandlerAndErrorHandlerAssignsHandlersAsGiven()
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $app = new App($accessLogHandler, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithAccessLogHandlerClassAndErrorHandlerClassAssignsDefaultHandlers()
    {
        $app = new App(AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndAccessLogHandlerClassAndErrorHandlerClassAssignsHandlersFromContainer()
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getAccessLogHandler')->willReturn($accessLogHandler);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($container, AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithMiddlewareBeforeAccessLogHandlerAndErrorHandlerAssignsDefaultErrorHandlerAsFirstHandlerFollowedByGivenHandlers()
    {
        $middleware = static function (ServerRequestInterface $request, callable $next) { };
        $accessLog = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $app = new App($middleware, $accessLog, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(5, $handlers);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[0]);
        $this->assertNotSame($errorHandler, $handlers[0]);
        $this->assertSame($middleware, $handlers[1]);
        $this->assertSame($accessLog, $handlers[2]);
        $this->assertSame($errorHandler, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithMultipleContainersAndAccessLogHandlerClassAndErrorHandlerClassAssignsHandlersFromLastContainer()
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getErrorHandler');

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getAccessLogHandler')->willReturn($accessLogHandler);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($unused, $container, AccessLogHandler::class, ErrorHandler::class, $unused);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }


    public function testConstructWithMultipleContainersAndMiddlewareAssignsDefaultHandlersFromLastContainerBeforeMiddleware()
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getAccessLogHandler');
        $unused->expects($this->never())->method('getErrorHandler');

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('getAccessLogHandler')->willReturn($accessLogHandler);
        $container->expects($this->once())->method('getErrorHandler')->willReturn($errorHandler);

        $app = new App($unused, $container, $middleware, $unused);

        $ref = new ReflectionProperty($app, 'handler');
        $ref->setAccessible(true);
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(MiddlewareHandler::class, $handler);
        $ref = new ReflectionProperty($handler, 'handlers');
        $ref->setAccessible(true);
        $handlers = $ref->getValue($handler);

        if (PHP_VERSION_ID >= 80100) {
            $first = array_shift($handlers);
            $this->assertInstanceOf(FiberHandler::class, $first);
        }

        $this->assertCount(4, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[3]);
    }

    public function testConstructWithAccessLogHandlerOnlyThrows()
    {
        $accessLogHandler = new AccessLogHandler();

        $this->expectException(\TypeError::class);
        new App($accessLogHandler);
    }

    public function testConstructWithAccessLogHandlerFollowedByMiddlewareThrows()
    {
        $accessLogHandler = new AccessLogHandler();
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $this->expectException(\TypeError::class);
        new App($accessLogHandler, $middleware);
    }

    public function testRunWillReportListeningAddressAndRunLoopWithSocketServer()
    {
        $socket = @stream_socket_server('127.0.0.1:8080');
        if ($socket === false) {
            $this->markTestSkipped('Listen address :8080 already in use');
        }
        fclose($socket);

        $app = new App();

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $this->expectOutputRegex('/' . preg_quote('Listening on http://127.0.0.1:8080' . PHP_EOL, '/') . '.*/');
        $app->run();
    }

    public function testRunWillReportListeningAddressFromContainerEnvironmentAndRunLoopWithSocketServer()
    {
        $socket = @stream_socket_server('127.0.0.1:0');
        $addr = stream_socket_get_name($socket, false);
        fclose($socket);

        $container = new Container([
            'X_LISTEN' => $addr
        ]);

        $app = new App($container);

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $this->expectOutputRegex('/' . preg_quote('Listening on http://' . $addr . PHP_EOL, '/') . '.*/');
        $app->run();
    }

    public function testRunWillReportListeningAddressFromContainerEnvironmentWithRandomPortAndRunLoopWithSocketServer()
    {
        $container = new Container([
            'X_LISTEN' => '127.0.0.1:0'
        ]);

        $app = new App($container);

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $this->expectOutputRegex('/' . preg_quote('Listening on http://127.0.0.1:', '/') . '\d+' . PHP_EOL . '.*/');
        $app->run();
    }

    public function testRunWillRestartLoopUntilSocketIsClosed()
    {
        $container = new Container([
            'X_LISTEN' => '127.0.0.1:0'
        ]);

        $app = new App($container);

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);

            Loop::futureTick(function () use ($socket) {
                Loop::removeReadStream($socket);
                fclose($socket);

                Loop::stop();
            });

            Loop::stop();
        });

        $this->expectOutputRegex('/' . preg_quote('Warning: Loop restarted. Upgrade to react/async v4 recommended for production use.' . PHP_EOL, '/') . '$/');
        $app->run();
    }

    /**
     * @requires function pcntl_signal
     * @requires function posix_kill
     */
    public function testRunWillStopWhenReceivingSigint()
    {
        $container = new Container([
            'X_LISTEN' => '127.0.0.1:0'
        ]);

        $app = new App($container);

        Loop::futureTick(function () {
            posix_kill(getmypid(), defined('SIGINT') ? SIGINT : 2);
        });

        $this->expectOutputRegex('/' . preg_quote('Received SIGINT, stopping loop' . PHP_EOL, '/') . '$/');
        $app->run();
    }

    /**
     * @requires function pcntl_signal
     * @requires function posix_kill
     */
    public function testRunWillStopWhenReceivingSigterm()
    {
        $container = new Container([
            'X_LISTEN' => '127.0.0.1:0'
        ]);

        $app = new App($container);

        Loop::futureTick(function () {
            posix_kill(getmypid(), defined('SIGTERM') ? SIGTERM : 15);
        });

        $this->expectOutputRegex('/' . preg_quote('Received SIGTERM, stopping loop' . PHP_EOL, '/') . '$/');
        $app->run();
    }

    public function testRunAppWithEmptyAddressThrows()
    {
        $container = new Container([
            'X_LISTEN' => ''
        ]);

        $app = new App($container);


        $this->expectException(\InvalidArgumentException::class);
        $app->run();
    }

    public function testRunAppWithBusyPortThrows()
    {
        $socket = @stream_socket_server('127.0.0.1:0');
        $addr = stream_socket_get_name($socket, false);

        if (@stream_socket_server($addr) !== false) {
            $this->markTestSkipped('System does not prevent listening on same address twice');
        }

        $container = new Container([
            'X_LISTEN' => $addr
        ]);

        $app = new App($container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to listen on');
        $app->run();
    }

    public function testRunOnceWillCreateRequestFromSapiThenRouteRequestAndThenSendResponseFromHandler()
    {
        $app = $this->createAppWithoutLogger();

        $response = new Response();
        $app->get('/', function () use ($response) {
            return $response;
        });

        $request = new ServerRequest('GET', 'http://example.com/');

        $sapi = $this->createMock(SapiHandler::class);
        $sapi->expects($this->once())->method('requestFromGlobals')->willReturn($request);
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

    public function testRunOnceWillCreateRequestFromSapiThenRouteRequestAndThenSendResponseFromDeferredHandler()
    {
        $app = $this->createAppWithoutLogger();

        $response = new Response();
        $app->get('/', function () use ($response) {
            return resolve($response);
        });

        $request = new ServerRequest('GET', 'http://example.com/');

        $sapi = $this->createMock(SapiHandler::class);
        $sapi->expects($this->once())->method('requestFromGlobals')->willReturn($request);
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

    public function testGetWithAccessLogHandlerAsMiddlewareThrows()
    {
        $app = new App();

        $this->expectException(\TypeError::class);
        $app->get('/', new AccessLogHandler(), function () { });
    }

    public function testGetWithAccessLogHandlerClassAsMiddlewareThrows()
    {
        $app = new App();

        $this->expectException(\TypeError::class);
        $app->get('/', AccessLogHandler::class, function () { });
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

    public function testHandleRequestWithProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed()
    {
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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

    public function testHandleRequestWithOptionsAsteriskRequestReturnsResponseFromMatchingEmptyRouteHandler()
    {
        $app = $this->createAppWithoutLogger();

        $app->options('', function () {
            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            );
        });

        $request = new ServerRequest('OPTIONS', 'http://localhost');
        $request = $request->withRequestTarget('*');

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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

    public function testHandleRequestWithMatchingRouteReturnsResponseWhenHandlerReturnsResponseAfterAwaitingPromiseResolvingWithResponse()
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            return await(resolve(new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "OK\n"
            )));
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

    public function testHandleRequestWithMatchingRouteReturnsPromiseResolvingWithResponseWhenHandlerReturnsResponseAfterAwaitingPromiseResolvingWithResponse()
    {
        if (PHP_VERSION_ID < 80100 || !function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires PHP 8.1+ with react/async 4+');
        }

        $app = $this->createAppWithoutLogger();

        $deferred = new Deferred();

        $app->get('/users', function () use ($deferred) {
            return await($deferred->promise());
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

        $this->assertNull($response);

        $deferred->resolve(new Response(
            200,
            [
                'Content-Type' => 'text/html'
            ],
            "OK\n"
        ));

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteAndRouteVariablesReturnsResponseFromHandlerWithRouteVariablesAssignedAsRequestAttributes()
    {
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        if (method_exists(PromiseInterface::class, 'catch')) {
            $this->markTestSkipped('Only supported for legacy Promise v2, Promise v3 always rejects with Throwable');
        }

        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerThrowsAfterAwaitingPromiseRejectingWithException()
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            return await(reject(new \RuntimeException('Foo')));
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

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerThrowsAfterAwaitingPromiseRejectingWithException()
    {
        if (PHP_VERSION_ID < 80100 || !function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires PHP 8.1+ with react/async 4+');
        }

        $app = $this->createAppWithoutLogger();

        $deferred = new Deferred();

        $line = __LINE__ + 1;
        $exception = new \RuntimeException('Foo');

        $app->get('/users', function () use ($deferred) {
            return await($deferred->promise());
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

        $this->assertNull($response);

        $deferred->reject($exception);

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassDoesNotExist()
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', 'UnknownClass');

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
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>BadMethodCallException</code> with message <code>Request handler class UnknownClass not found</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function provideInvalidClasses()
    {
        yield [
            InvalidConstructorPrivate::class,
            'Cannot instantiate class ' . InvalidConstructorPrivate::class
        ];

        yield [
            InvalidConstructorProtected::class,
            'Cannot instantiate class ' . InvalidConstructorProtected::class
        ];

        yield [
            InvalidAbstract::class,
            'Cannot instantiate abstract class ' . InvalidAbstract::class
        ];

        yield [
            InvalidInterface::class,
            'Cannot instantiate interface ' . InvalidInterface::class
        ];

        yield [
            InvalidTrait::class,
            'Cannot instantiate trait ' . InvalidTrait::class
        ];

        yield [
            InvalidConstructorUntyped::class,
            'Argument 1 ($value) of %s::__construct() has no type'
        ];

        yield [
            InvalidConstructorInt::class,
            'Argument 1 ($value) of %s::__construct() expects unsupported type int'
        ];

        if (PHP_VERSION_ID >= 80000) {
            yield [
                InvalidConstructorUnion::class,
                'Argument 1 ($value) of %s::__construct() expects unsupported type int|float'
            ];
        }

        if (PHP_VERSION_ID >= 80100) {
            yield [
                InvalidConstructorIntersection::class,
                'Argument 1 ($value) of %s::__construct() expects unsupported type Traversable&amp;ArrayAccess'
            ];
        }

        yield [
            InvalidConstructorUnknown::class,
            'Class UnknownClass not found'
        ];

        yield [
            InvalidConstructorSelf::class,
            'Argument 1 ($value) of %s::__construct() is recursive'
        ];
    }

    /**
     * @dataProvider provideInvalidClasses
     * @param class-string $class
     * @param string $error
     */
    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassIsInvalid(string $class, string $error)
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', $class);

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
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>BadMethodCallException</code> with message <code>Request handler class " . $class . " failed to load: $error</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassRequiresUnexpectedCallableParameter()
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $controller = new class {
            public function __invoke(int $value) { }
        };

        $app->get('/users', get_class($controller));

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
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>TypeError</code> with message <code>%s</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassHasNoInvokeMethod()
    {
        $app = $this->createAppWithoutLogger();

        $controller = new class { };

        $app->get('/users', get_class($controller));

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
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>BadMethodCallException</code> with message <code>Request handler class %s has no public __invoke() method</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testHandleRequestWithMatchingRouteReturnsPromiseWhichFulfillsWithInternalServerErrorResponseWhenHandlerReturnsPromiseWhichFulfillsWithWrongValue()
    {
        $app = $this->createAppWithoutLogger();

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
        $app = $this->createAppWithoutLogger();

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

    private function createAppWithoutLogger(): App
    {
        $app = new App();

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
