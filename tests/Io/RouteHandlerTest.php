<?php

namespace FrameworkX\Tests\Io;

use FastRoute\RouteCollector;
use FrameworkX\Container;
use FrameworkX\Io\MiddlewareHandler;
use FrameworkX\Io\RouteHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

class RouteHandlerTest extends TestCase
{
    public function testMapRouteWithControllerAddsRouteOnRouter(): void
    {
        $controller = function () { };

        $handler = new RouteHandler();

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $controller);

        $ref = new \ReflectionProperty($handler, 'routeCollector');
        $ref->setAccessible(true);
        $ref->setValue($handler, $router);

        $handler->map(['GET'], '/', $controller);
    }

    public function testMapRouteWithMiddlewareAndControllerAddsRouteWithMiddlewareHandlerOnRouter(): void
    {
        $middleware = function () { };
        $controller = function () { };

        $handler = new RouteHandler();

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', new MiddlewareHandler([$middleware, $controller]));

        $ref = new \ReflectionProperty($handler, 'routeCollector');
        $ref->setAccessible(true);
        $ref->setValue($handler, $router);

        $handler->map(['GET'], '/', $middleware, $controller);
    }

    public function testMapRouteWithClassNameAddsRouteOnRouterWithControllerCallableFromContainer(): void
    {
        $controller = function () { };

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('callable')->with('stdClass')->willReturn($controller);

        assert($container instanceof Container);
        $handler = new RouteHandler($container);

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $controller);

        $ref = new \ReflectionProperty($handler, 'routeCollector');
        $ref->setAccessible(true);
        $ref->setValue($handler, $router);

        $handler->map(['GET'], '/', \stdClass::class);
    }

    public function testMapRouteWithContainerAndControllerAddsRouteOnRouterWithControllerOnly(): void
    {
        $controller = function () { };

        $handler = new RouteHandler();

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $controller);

        $ref = new \ReflectionProperty($handler, 'routeCollector');
        $ref->setAccessible(true);
        $ref->setValue($handler, $router);

        $handler->map(['GET'], '/', new Container(), $controller);
    }

    public function testMapRouteWithContainerAndControllerClassNameAddsRouteOnRouterWithControllerCallableFromContainer(): void
    {
        $controller = function () { };

        $container = $this->createMock(Container::class);
        $container->expects($this->once())->method('callable')->with('stdClass')->willReturn($controller);

        $handler = new RouteHandler();

        $router = $this->createMock(RouteCollector::class);
        $router->expects($this->once())->method('addRoute')->with(['GET'], '/', $controller);

        $ref = new \ReflectionProperty($handler, 'routeCollector');
        $ref->setAccessible(true);
        $ref->setValue($handler, $router);

        assert($container instanceof Container);
        $handler->map(['GET'], '/', $container, \stdClass::class);
    }

    public function testHandleRequestWithProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $request = $request->withRequestTarget('http://example.com/');

        $handler = new RouteHandler();
        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 400: Proxy Requests Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check your settings and retry.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithConnectProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed(): void
    {
        $request = new ServerRequest('CONNECT', 'example.com:80');
        $request = $request->withRequestTarget('example.com:80');

        $handler = new RouteHandler();
        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertStringContainsString("<title>Error 400: Proxy Requests Not Allowed</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Please check your settings and retry.</p>\n", (string) $response->getBody());
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandler(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', function () use ($response) { return $response; });

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingPsr15MiddlewareHandler(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', new class ($response) implements MiddlewareInterface {

            /**
             * @var Response
             */
            private $response;

            public function __construct(Response $response)
            {
                $this->response = $response;
            }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        });

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerClass(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $controller = new class {
            /** @var ?Response */
            public static $response;
            public function __invoke(): ?Response {
                return self::$response;
            }
        };
        $controller::$response = $response;

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', $controller);

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerClassName(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $controller = new class {
            /** @var ?Response */
            public static $response;
            public function __invoke(): ?Response
            {
                return self::$response;
            }
        };
        $controller::$response = $response;

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($controller));

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerClassNameWithOptionalConstructor(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $controller = new class {
            /** @var ?Response */
            public static $response;
            public function __construct(int $value = null)
            {
                assert($value === null);
            }
            public function __invoke(): ?Response
            {
                return self::$response;
            }
        };
        $controller::$response = $response;

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($controller));

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerClassNameWithNullableConstructor(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $controller = new class(null) {
            /** @var ?Response */
            public static $response;
            public function __construct(?int $value)
            {
                assert($value === null);
            }
            public function __invoke(): ?Response
            {
                return self::$response;
            }
        };
        $controller::$response = $response;

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($controller));

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerClassNameWithRequiredResponseInConstructor(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response(500)) {
            /** @var ?Response */
            public static $response;
            public function __construct(Response $response)
            {
                self::$response = $response;
            }
            public function __invoke(): ?Response
            {
                return self::$response;
            }
        };

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($controller));

        $ret = $handler($request);

        $this->assertSame($controller::$response, $ret);
    }

    public function testHandleRequestWithGetRequestReturnsResponseFromMatchingHandlerWithClassNameMiddleware(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $middleware = new class {
            public function __invoke(ServerRequestInterface $request, callable $next): Response
            {
                return $next($request);
            }
        };

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($middleware), function () use ($response) { return $response; });

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestTwiceWithGetRequestCallsSameHandlerInstanceFromMatchingHandlerClassName(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class {
            /** @var int */
            private $called = 0;
            public function __invoke(): int
            {
                return ++$this->called;
            }
        };

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', get_class($controller));

        $ret = $handler($request);
        $this->assertEquals(1, $ret);

        $ret = $handler($request);
        $this->assertEquals(2, $ret);
    }

    public function testHandleRequestWithGetRequestWithHttpUrlInPathReturnsResponseFromMatchingHandler(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/http://localhost/');
        $response = new Response(200, [], '');

        $handler = new RouteHandler();
        $handler->map(['GET'], '/http://localhost/', function () use ($response) { return $response; });

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithOptionsAsteriskRequestReturnsResponseFromMatchingAsteriskHandler(): void
    {
        $request = new ServerRequest('OPTIONS', 'http://example.com');
        $request = $request->withRequestTarget('*');
        $response = new Response(200, [], '');

        $handler = new RouteHandler();
        $handler->map(['OPTIONS'], '*', function () use ($response) { return $response; });

        $ret = $handler($request);

        $this->assertSame($response, $ret);
    }

    public function testHandleRequestWithContainerOnlyThrows(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $handler = new RouteHandler();
        $handler->map(['GET'], '/', new Container());

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container should not be used as final request handler');
        $handler($request);
    }
}
