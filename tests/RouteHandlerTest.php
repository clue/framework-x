<?php

namespace FrameworkX\Tests;

use FastRoute\RouteCollector;
use FrameworkX\MiddlewareHandler;
use FrameworkX\RouteHandler;
use PHPUnit\Framework\TestCase;

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
}
