<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\App;
use FrameworkX\Container;
use FrameworkX\ErrorHandler;
use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\Io\ReactiveHandler;
use FrameworkX\Io\RouteHandler;
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
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionProperty;
use function React\Async\async; // @phpstan-ignore-line
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class AppTest extends TestCase
{
    public function testConstructAssignsDefaultMiddleware(): void
    {
        $app = new App();

        $ref = new \ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new \ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithMiddlewareAssignsGivenMiddleware(): void
    {
        $middleware = function () { };
        $app = new App($middleware);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(4, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[3]);
    }

    public function testConstructWithContainerMockAssignsDefaultHandlersFromContainer(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $sapi = $this->createMock(ReactiveHandler::class);

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);
        $container->expects($this->once())->method('getSapi')->willReturn($sapi);
        assert($container instanceof Container);

        $app = new App($container);

        $ref = new \ReflectionProperty($app, 'sapi');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ret = $ref->getValue($app);

        $this->assertSame($sapi, $ret);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithContainerInstanceAssignsDefaultHandlersAndContainerForRouteHandlerOnly(): void
    {
        $container = new Container([]);
        $app = new App($container);

        $ref = new \ReflectionProperty($app, 'sapi');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ret = $ref->getValue($app);

        $this->assertSame($container->getSapi(), $ret);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);

        $routeHandler = $handlers[2];
        $ref = new ReflectionProperty($routeHandler, 'container');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $this->assertSame($container, $ref->getValue($routeHandler));
    }

    public function testConstructWithContainerAndMiddlewareClassNameAssignsCallableFromContainerAsMiddleware(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('callable')->with('stdClass')->willReturn($middleware);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($container instanceof Container);
        $app = new App($container, \stdClass::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(4, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($routeHandler, $handlers[3]);
    }

    public function testConstructWithErrorHandlerOnlyAssignsErrorHandlerAfterDefaultAccessLogHandler(): void
    {
        $errorHandler = new ErrorHandler();

        $app = new App($errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithErrorHandlerClassOnlyAssignsErrorHandlerAfterDefaultAccessLogHandler(): void
    {
        $app = new App(ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndErrorHandlerAssignsErrorHandlerAfterDefaultAccessLogHandler(): void
    {
        $errorHandler = new ErrorHandler();

        $app = new App(new Container(), $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndErrorHandlerClassAssignsErrorHandlerFromContainerAfterDefaultAccessLogHandler(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($container instanceof Container);
        $app = new App($container, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithMultipleContainersAndErrorHandlerClassAssignsErrorHandlerFromLastContainerBeforeErrorHandlerAfterDefaultAccessLogHandler(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getObject');

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($unused instanceof Container);
        assert($container instanceof Container);
        $app = new App($unused, $unused, $container, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithMultipleContainersAndMiddlewareAssignsErrorHandlerFromLastContainerBeforeMiddlewareAfterDefaultAccessLogHandler(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getObject');

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($unused instanceof Container);
        assert($container instanceof Container);
        $app = new App($unused, $unused, $container, $middleware);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(4, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($routeHandler, $handlers[3]);
    }

    public function testConstructWithMiddlewareAndErrorHandlerAssignsGivenErrorHandlerAfterMiddlewareAndDefaultAccessLogHandlerAndErrorHandlerFirst(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };
        $errorHandler = new ErrorHandler();

        $app = new App($middleware, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(5, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertNotSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($errorHandler, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithMultipleContainersAndMiddlewareAndErrorHandlerClassAssignsDefaultErrorHandlerFromLastContainerBeforeMiddlewareAndErrorHandlerFromLastContainerAfterDefaultAccessLogHandler(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getObject');
        assert($unused instanceof Container);

        $accessLogHandler = new AccessLogHandler();
        $errorHandler1 = new ErrorHandler();
        $container1 = $this->createMock(Container::class);
        $container1->expects($this->exactly(2))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler1],
        ]);
        assert($container1 instanceof Container);

        $errorHandler2 = new ErrorHandler();
        $container2 = $this->createMock(Container::class);
        $container2->expects($this->once())->method('getObject')->with(ErrorHandler::class)->willReturn($errorHandler2);
        assert($container2 instanceof Container);

        $routeHandler = $this->createMock(RouteHandler::class);
        $container3 = $this->createMock(Container::class);
        $container3->expects($this->once())->method('getObject')->with(RouteHandler::class)->willReturn($routeHandler);
        assert($container3 instanceof Container);

        $app = new App($unused, $container1, $middleware, $container2, ErrorHandler::class, $unused, $container3);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(5, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler1, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($errorHandler2, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithAccessLogHandlerAndErrorHandlerAssignsHandlersAsGiven(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $app = new App($accessLogHandler, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithAccessLogHandlerToDevNullAndErrorHandlerWillRemoveAccessLogHandler(): void
    {
        $accessLogHandler = $this->createMock(AccessLogHandler::class);
        $accessLogHandler->expects($this->once())->method('isDevNull')->willReturn(true);
        assert(is_callable($accessLogHandler));

        $errorHandler = new ErrorHandler();

        $app = new App($accessLogHandler, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(2, $handlers);
        $this->assertSame($errorHandler, $handlers[0]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[1]);
    }

    public function testConstructWithAccessLogHandlerClassAndErrorHandlerClassAssignsDefaultHandlers(): void
    {
        $app = new App(AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndAccessLogHandlerClassAndErrorHandlerClassAssignsHandlersFromContainer(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($container instanceof Container);
        $app = new App($container, AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithContainerAndAccessLogHandlerClassAndErrorHandlerClassWillUseContainerToGetAccessLogHandlerAndWillSkipAccessLogHandlerToDevNull(): void
    {
        $accessLogHandler = $this->createMock(AccessLogHandler::class);
        $accessLogHandler->expects($this->once())->method('isDevNull')->willReturn(true);

        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($container instanceof Container);
        $app = new App($container, AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(2, $handlers);
        $this->assertSame($errorHandler, $handlers[0]);
        $this->assertSame($routeHandler, $handlers[1]);
    }

    public function testConstructWithMiddlewareBeforeAccessLogHandlerAndErrorHandlerAssignsDefaultErrorHandlerAsFirstHandlerFollowedByGivenHandlers(): void
    {
        $middleware = static function (ServerRequestInterface $request, callable $next) { };
        $accessLog = new AccessLogHandler();
        $errorHandler = new ErrorHandler();

        $app = new App($middleware, $accessLog, $errorHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(5, $handlers);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[0]);
        $this->assertNotSame($errorHandler, $handlers[0]);
        $this->assertSame($middleware, $handlers[1]);
        $this->assertSame($accessLog, $handlers[2]);
        $this->assertSame($errorHandler, $handlers[3]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[4]);
    }

    public function testConstructWithMultipleContainersAndAccessLogHandlerClassAndErrorHandlerClassAssignsHandlersFromLastContainer(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getObject');

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(3))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($unused instanceof Container);
        assert($container instanceof Container);
        $app = new App($unused, $unused, $container, AccessLogHandler::class, ErrorHandler::class);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }


    public function testConstructWithMultipleContainersAndMiddlewareAssignsDefaultHandlersFromLastContainerBeforeMiddleware(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $accessLogHandler = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);

        $unused = $this->createMock(Container::class);
        $unused->expects($this->never())->method('getObject');

        $container = $this->createMock(Container::class);
        $container->expects($this->exactly(4))->method('getObject')->willReturnMap([
            [AccessLogHandler::class, $accessLogHandler],
            [ErrorHandler::class, $errorHandler],
            [Container::class, $container],
            [RouteHandler::class, $routeHandler],
        ]);

        assert($unused instanceof Container);
        assert($container instanceof Container);
        $app = new App($unused, $unused, $container, Container::class, $middleware);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(4, $handlers);
        $this->assertSame($accessLogHandler, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($middleware, $handlers[2]);
        $this->assertSame($routeHandler, $handlers[3]);
    }

    public function testConstructWithRouteHandlerOnlyAssignsRouteHandlerAfterDefaultErrorHandler(): void
    {
        $routeHandler = $this->createMock(RouteHandler::class);
        assert($routeHandler instanceof RouteHandler);

        $app = new App($routeHandler);

        $ref = new ReflectionProperty($app, 'router');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);

        $this->assertSame($routeHandler, $handler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithRouteHandlerClassOnlyAssignsRouteHandlerAfterDefaultErrorHandler(): void
    {
        $app = new App(RouteHandler::class);

        $ref = new ReflectionProperty($app, 'router');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);

        $this->assertInstanceOf(RouteHandler::class, $handler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertInstanceOf(AccessLogHandler::class, $handlers[0]);
        $this->assertInstanceOf(ErrorHandler::class, $handlers[1]);
        $this->assertInstanceOf(RouteHandler::class, $handlers[2]);
    }

    public function testConstructWithContainerAndAccessLogHandlerAndErrorHandlerAndRouteHandlerAssignsGivenHandlersWithoutUsingContainer(): void
    {
        $accessLog = new AccessLogHandler();
        $errorHandler = new ErrorHandler();
        $routeHandler = $this->createMock(RouteHandler::class);
        assert($routeHandler instanceof RouteHandler);

        $container = $this->createMock(Container::class);
        $container->expects($this->never())->method('getObject');
        assert($container instanceof Container);

        $app = new App($container, $accessLog, $errorHandler, $routeHandler);

        $ref = new ReflectionProperty($app, 'handler');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handler = $ref->getValue($app);
        assert($handler instanceof MiddlewareHandler);

        $ref = new ReflectionProperty($handler, 'handlers');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $handlers = $ref->getValue($handler);
        assert(is_array($handlers));

        $this->assertCount(3, $handlers);
        $this->assertSame($accessLog, $handlers[0]);
        $this->assertSame($errorHandler, $handlers[1]);
        $this->assertSame($routeHandler, $handlers[2]);
    }

    public function testConstructWithAccessLogHandlerOnlyThrows(): void
    {
        $accessLogHandler = new AccessLogHandler();

        $this->expectException(\TypeError::class);
        new App($accessLogHandler);
    }

    public function testConstructWithAccessLogHandlerFollowedByMiddlewareThrows(): void
    {
        $accessLogHandler = new AccessLogHandler();
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $this->expectException(\TypeError::class);
        new App($accessLogHandler, $middleware);
    }

    public function testConstructWithRouteHandlerInstanceFollowedByMiddlewareThrows(): void
    {
        $routeHandler = $this->createMock(RouteHandler::class);
        assert($routeHandler instanceof RouteHandler);

        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $this->expectException(\TypeError::class);
        new App($routeHandler, $middleware);
    }

    public function testConstructWithRouteHandlerClassNameFollowedByMiddlewareThrows(): void
    {
        $middleware = function (ServerRequestInterface $request, callable $next) { };

        $this->expectException(\TypeError::class);
        new App(RouteHandler::class, $middleware);
    }

    public function testConstructWithContainerWithListenAddressWillPassListenAddressToReactiveHandler(): void
    {
        $container = new Container([
            'X_LISTEN' => '0.0.0.0:8081'
        ]);

        $app = new App($container);

        // $sapi = $app->sapi;
        $ref = new \ReflectionProperty($app, 'sapi');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $sapi = $ref->getValue($app);
        assert($sapi instanceof ReactiveHandler);

        // $listenAddress = $sapi->listenAddress;
        $ref = new \ReflectionProperty($sapi, 'listenAddress');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $listenAddress = $ref->getValue($sapi);

        $this->assertEquals('0.0.0.0:8081', $listenAddress);
    }

    public function testRunWillExecuteRunOnSapiHandlerFromContainer(): void
    {
        $sapi = $this->createMock(ReactiveHandler::class);
        $sapi->expects($this->once())->method('run');

        $container = new Container([
            ReactiveHandler::class => $sapi
        ]);

        $app = new App($container);

        $app->run();
    }

    public function testGetMethodAddsGetRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->get('/', function () { });
    }

    public function testHeadMethodAddsHeadRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['HEAD'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->head('/', function () { });
    }

    public function testPostMethodAddsPostRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['POST'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->post('/', function () { });
    }

    public function testPutMethodAddsPutRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PUT'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->put('/', function () { });
    }

    public function testPatchMethodAddsPatchRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['PATCH'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->patch('/', function () { });
    }

    public function testDeleteMethodAddsDeleteRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['DELETE'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->delete('/', function () { });
    }

    public function testOptionsMethodAddsOptionsRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['OPTIONS'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->options('/', function () { });
    }

    public function testAnyMethodAddsRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->any('/', function () { });
    }

    public function testMapMethodAddsRouteOnRouter(): void
    {
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST'], '/', $this->anything());
        assert($router instanceof RouteHandler);

        $app = new App($router);

        $app->map(['GET', 'POST'], '/', function () { });
    }

    public function testGetWithAccessLogHandlerAsMiddlewareThrows(): void
    {
        $app = new App();

        $this->expectException(\TypeError::class);
        $app->get('/', new AccessLogHandler(), function () { });
    }

    public function testGetWithAccessLogHandlerClassAsMiddlewareThrows(): void
    {
        $app = new App();

        $this->expectException(\TypeError::class);
        $app->get('/', AccessLogHandler::class, function () { });
    }

    public function testRedirectMethodAddsAnyRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithTargetLocation(): void
    {
        $handler = null;
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->callback(function ($fn) use (&$handler) {
            $handler = $fn;
            return true;
        }));
        assert($router instanceof RouteHandler);

        $app = new App($router);

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

    public function testRedirectMethodWithCustomRedirectCodeAddsAnyRouteOnRouterWhichWhenInvokedReturnsRedirectResponseWithCustomRedirectCode(): void
    {
        $handler = null;
        $router = $this->createMock(RouteHandler::class);
        $router->expects($this->once())->method('map')->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/', $this->callback(function ($fn) use (&$handler) {
            $handler = $fn;
            return true;
        }));
        assert($router instanceof RouteHandler);

        $app = new App($router);

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

    public function testInvokeWithProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed(): void
    {
        $app = $this->createAppWithoutLogger();

        $request = new ServerRequest('GET', 'http://google.com/');
        $request = $request->withRequestTarget('http://google.com/');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 400: Proxy Requests Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check your settings and retry.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithUnknownRouteReturnsResponseWithFileNotFoundMessage(): void
    {
        $app = $this->createAppWithoutLogger();

        $request = new ServerRequest('GET', 'http://localhost/invalid');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithInvalidRequestMethodReturnsResponseWithSingleMethodNotAllowedMessage(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () { });

        $request = new ServerRequest('POST', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code> request.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithInvalidRequestMethodReturnsResponseWithMultipleMethodNotAllowedMessage(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () { });
        $app->head('/users', function () { });
        $app->post('/users', function () { });

        $request = new ServerRequest('DELETE', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals('GET, HEAD, POST', $response->getHeaderLine('Allow'));
        $this->assertStringContainsString("<title>Error 405: Method Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check the URL in the address bar and try again with <code>GET</code>/<code>HEAD</code>/<code>POST</code> request.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsResponseFromMatchingRouteHandler(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithOptionsAsteriskRequestReturnsResponseFromMatchingAsteriskRouteHandler(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->options('*', function () {
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithOptionsAsteriskRequestReturnsResponseFromMatchingDeprecatedEmptyRouteHandler(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsPromiseWhichFulfillsWithResponse(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsNeverWhenHandlerReturnsPendingPromise(): void
    {
        if (!function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires reactphp/async v4 (PHP 8.1+)');
        }

        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            return new Promise(function () { });
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        /** @var PromiseInterface<never> $promise */
        $promise = async($app)($request); // @phpstan-ignore-line

        $resolved = false;
        $promise->then(function () use (&$resolved) {
            $resolved = true;
        }, function () use (&$resolved) {
            $resolved = true;
        });

        $this->assertFalse($resolved);
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsCoroutineWhichReturnsResponseWithoutYielding(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            if (false) { // @phpstan-ignore-line
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterYieldingResolvedPromise(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsCoroutineWhichReturnsResponseAfterCatchingExceptionFromYieldingRejectedPromise(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsNeverWhenHandlerReturnsCoroutineThatYieldsPendingPromise(): void
    {
        if (!function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires reactphp/async v4 (PHP 8.1+)');
        }

        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            yield new Promise(function () { });
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        /** @var PromiseInterface<never> $promise */
        $promise = async($app)($request); // @phpstan-ignore-line

        $resolved = false;
        $promise->then(function () use (&$resolved) {
            $resolved = true;
        }, function () use (&$resolved) {
            $resolved = true;
        });

        $this->assertFalse($resolved);
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsResponseAfterAwaitingPromiseResolvedWithResponse(): void
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

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("OK\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsResponseWhenHandlerReturnsResponseAfterAwaitingPromiseResolvingDeferredWithResponse(): void
    {
        if (!function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires reactphp/async v4 (PHP 8.1+)');
        }

        $app = $this->createAppWithoutLogger();

        $deferred = new Deferred();

        $app->get('/users', function () use ($deferred) {
            return await($deferred->promise());
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        /** @var PromiseInterface<ResponseInterface> $promise */
        $promise = async($app)($request); // @phpstan-ignore-line

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

    public function testInvokeWithMatchingRouteAndRouteVariablesReturnsResponseFromHandlerWithRouteVariablesAssignedAsRequestAttributes(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users/{name}', function (ServerRequestInterface $request) {
            $name = $request->getAttribute('name');
            assert(is_string($name));

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html'
                ],
                "Hello $name\n"
            );
        });

        $request = new ServerRequest('GET', 'http://localhost/users/alice');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("Hello alice\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerThrowsException(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsPromiseWhichRejectsWithException(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            return reject(new \RuntimeException('Foo'));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsRejectedPromise(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            yield reject(new \RuntimeException('Foo'));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichThrowsExceptionWithoutYielding(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 5;
        $app->get('/users', function () {
            if (false) { // @phpstan-ignore-line
                yield;
            }
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichThrowsExceptionAfterYielding(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 3;
        $app->get('/users', function () {
            yield resolve(null);
            throw new \RuntimeException('Foo');
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerThrowsAfterAwaitingPromiseRejectedWithException(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $app->get('/users', function () {
            await(reject(new \RuntimeException('Foo')));
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>RuntimeException</code> with message <code>Foo</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerThrowsAfterAwaitingPromiseRejectingDeferredWithException(): void
    {
        if (!function_exists('React\Async\async')) {
            $this->markTestSkipped('Requires reactphp/async v4 (PHP 8.1+)');
        }

        $app = $this->createAppWithoutLogger();

        $deferred = new Deferred();

        $line = __LINE__ + 1;
        $exception = new \RuntimeException('Foo');

        $app->get('/users', function () use ($deferred) {
            return await($deferred->promise());
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        /** @var PromiseInterface<ResponseInterface> $promise */
        $promise = async($app)($request); // @phpstan-ignore-line

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

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichReturnsNull(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            $value = yield resolve(null);
            return $value;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsCoroutineWhichYieldsNullImmediately(): void
    {
        $app = $this->createAppWithoutLogger();

        // expect error on next line (should yield PromiseInterface)
        // return on same line because PHP < 8.4 reports error on statement *after* invalid yield
        $line = __LINE__ + 2;
        $app->get('/users', function () {
            return yield null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to yield <code>React\Promise\PromiseInterface</code> but got <code>null</code> near or before <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsWrongValue(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            return null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassDoesNotExist(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', 'UnknownClass'); // @phpstan-ignore-line

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>Error</code> with message <code>Request handler class UnknownClass failed to load: Class UnknownClass not found</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function provideInvalidClasses(): \Generator
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
            'Argument #1 ($value) of %s::__construct() requires container config, none given'
        ];

        yield [
            InvalidConstructorInt::class,
            'Argument #1 ($value) of %s::__construct() requires container config with type int, none given'
        ];

        if (PHP_VERSION_ID >= 80000) {
            yield [
                InvalidConstructorUnion::class,
                'Argument #1 ($value) of %s::__construct() requires container config with type int|float, none given'
            ];
        }

        if (PHP_VERSION_ID >= 80100) {
            yield [
                InvalidConstructorIntersection::class,
                'Argument #1 ($value) of %s::__construct() requires container config with type Traversable&amp;ArrayAccess, none given'
            ];
        }

        yield [
            InvalidConstructorUnknown::class,
            'Class UnknownClass not found'
        ];

        yield [
            InvalidConstructorSelf::class,
            'Argument #1 ($value) of %s::__construct() is recursive'
        ];
    }

    /**
     * @dataProvider provideInvalidClasses
     * @param class-string $class
     * @param string $error
     */
    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassIsInvalid(string $class, string $error): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', $class);

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>Error</code> with message <code>Request handler class $class failed to load: $error</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassRequiresUnexpectedCallableParameter(): void
    {
        $app = $this->createAppWithoutLogger();

        $line = __LINE__ + 2;
        $controller = new class {
            public function __invoke(int $value): void { }
        };

        $app->get('/users', get_class($controller));

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>TypeError</code> with message <code>%s</code> in <code title=\"See " . __FILE__ . " line $line\">AppTest.php:$line</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerClassHasNoInvokeMethod(): void
    {
        $app = $this->createAppWithoutLogger();

        $controller = new class { };

        $app->get('/users', get_class($controller));

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got uncaught <code>Error</code> with message <code>Request handler class@anonymous has no public __invoke() method</code> in <code title=\"See %s\">Container.php:%d</code>.</p>\n%a", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsPromiseWhichFulfillsWithWrongValue(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            return resolve(null);
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithMatchingRouteReturnsInternalServerErrorResponseWhenHandlerReturnsWrongValueAfterYielding(): void
    {
        $app = $this->createAppWithoutLogger();

        $app->get('/users', function () {
            yield resolve(true);
            return null;
        });

        $request = new ServerRequest('GET', 'http://localhost/users');

        $response = $app($request);
        assert($response instanceof ResponseInterface);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 500: Internal Server Error</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>The requested page failed to load, please try again later.</p>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Expected request handler to return <code>Psr\Http\Message\ResponseInterface</code> but got <code>null</code>.</p>\n", (string) $response->getBody());
    }

    private function createAppWithoutLogger(callable ...$middleware): App
    {
        return new App(
            new AccessLogHandler(DIRECTORY_SEPARATOR !== '\\' ? '/dev/null' : __DIR__ . '\\nul'),
            new ErrorHandler(),
            ...$middleware
        );
    }
}
