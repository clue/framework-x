<?php

namespace FrameworkX\Tests;

use PHPUnit\Framework\TestCase;
use FrameworkX\Container;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

class ContainerTest extends TestCase
{
    public function testInvokeContainerAsMiddlewareReturnsFromNextRequestHandler()
    {
        $request = new ServerRequest('GET', 'http://example.com/http://localhost/');
        $response = new Response(200, [], '');

        $container = new Container();
        $ret = $container($request, function () use ($response) { return $response; });

        $this->assertSame($response, $ret);
    }

    public function testInvokeContainerAsFinalRequestHandlerThrows()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container should not be used as final request handler');
        $container($request);
    }
}
