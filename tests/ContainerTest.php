<?php

namespace FrameworkX\Tests;

use FrameworkX\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;

class ContainerTest extends TestCase
{
    public function testCallableReturnsCallableForClassNameViaAutowiring()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class {
            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200);
            }
        };

        $container = new Container();

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCallableReturnsCallableForClassNameViaAutowiringWithConfigurationForDependency()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => (object)['name' => 'Alice']
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameViaAutowiringWithFactoryFunctionForDependency()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => function () {
                return (object)['name' => 'Alice'];
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function testCallableTwiceReturnsCallableForClassNameViaAutowiringWithFactoryFunctionForDependencyWillCallFactoryOnlyOnce()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => function () {
                static $called = 0;
                return (object)['num' => ++$called];
            }
        ]);

        $callable = $container->callable(get_class($controller));

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"num":1}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithExplicitlyMappedSubclassForDependency()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $dto = new class extends \stdClass { };
        $dto->name = 'Alice';

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => get_class($dto),
            get_class($dto) => $dto
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithSubclassMappedFromFactoryForDependency()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $dto = new class extends \stdClass { };
        $dto->name = 'Alice';

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => function () use ($dto) { return get_class($dto); },
            get_class($dto) => function () use ($dto) { return $dto; }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function testCtorThrowsWhenMapContainsInvalidInteger()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected integer');

        new Container([
            \stdClass::class => 42
        ]);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidClassName()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function () { return 'invalid'; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Class invalid not found');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidInteger()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function () { return 42; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Factory for stdClass returned unexpected integer');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryIsRecursive()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => \stdClass::class
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Factory for stdClass is recursive');
        $callable($request);
    }

    public function testInvokeContainerAsMiddlewareReturnsFromNextRequestHandler()
    {
        $request = new ServerRequest('GET', 'http://example.com/');
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
