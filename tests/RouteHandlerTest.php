<?php

namespace FrameworkX\Tests;

use FastRoute\RouteCollector;
use FrameworkX\MiddlewareHandler;
use FrameworkX\RouteHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;

class RouteHandlerTest extends TestCase
{
    public function testMapRouteWithControllerAddsRouteOnRouter()
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

    public function testMapRouteWithMiddlewareAndControllerAddsRouteWithMiddlewareHandlerOnRouter()
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

    public function testHandleRequestWithProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed()
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

    public function testHandleRequestWithConnectProxyRequestReturnsResponseWithMessageThatProxyRequestsAreNotAllowed()
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
}
