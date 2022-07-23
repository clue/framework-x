<?php

namespace FrameworkX\Tests;

use FrameworkX\AccessLogHandler;
use FrameworkX\Container;
use FrameworkX\ErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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

    public function testCallableReturnsCallableForClassNameWithSubclassMappedFromFactoryWithClassDependency()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke()
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (\stdClass $dto) {
                return new Response(200, [], json_encode($dto));
            },
            \stdClass::class => function () { return (object)['name' => 'Alice']; }
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresInvalidClassName()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function (self $instance) { return $instance; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Class self not found');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresUntypedArgument()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function ($data) { return $data; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($data) of {closure}() has no type');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresRecursiveClass()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function (\stdClass $data) { return $data; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($data) of {closure}() is recursive');
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

    public function testCallableReturnsCallableForClassNameViaPsrContainer()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class {
            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200);
            }
        };

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->never())->method('has');
        $psr->expects($this->once())->method('get')->with(get_class($controller))->willReturn($controller);

        $container = new Container($psr);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidClassNameViaPsrContainer()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $exception = new class('Unable to load class') extends \RuntimeException implements NotFoundExceptionInterface { };

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->never())->method('has');
        $psr->expects($this->once())->method('get')->with('FooBar')->willThrowException($exception);

        $container = new Container($psr);

        $callable = $container->callable('FooBar');

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Request handler class FooBar failed to load: Unable to load class');
        $callable($request);
    }

    public function testGetAccessLogHandlerReturnsDefaultAccessLogHandlerInstance()
    {
        $container = new Container([]);

        $accessLogHandler = $container->getAccessLogHandler();

        $this->assertInstanceOf(AccessLogHandler::class, $accessLogHandler);
    }

    public function testGetAccessLogHandlerReturnsAccessLogHandlerInstanceFromMap()
    {
        $accessLogHandler = new AccessLogHandler();

        $container = new Container([
            AccessLogHandler::class => $accessLogHandler
        ]);

        $ret = $container->getAccessLogHandler();

        $this->assertSame($accessLogHandler, $ret);
    }

    public function testGetAccessLogHandlerReturnsAccessLogHandlerInstanceFromPsrContainer()
    {
        $accessLogHandler = new AccessLogHandler();

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(AccessLogHandler::class)->willReturn(true);
        $psr->expects($this->once())->method('get')->with(AccessLogHandler::class)->willReturn($accessLogHandler);

        $container = new Container($psr);

        $ret = $container->getAccessLogHandler();

        $this->assertSame($accessLogHandler, $ret);
    }

    public function testGetAccessLogHandlerReturnsDefaultAccessLogHandlerInstanceIfPsrContainerHasNoEntry()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(AccessLogHandler::class)->willReturn(false);
        $psr->expects($this->never())->method('get');

        $container = new Container($psr);

        $accessLogHandler = $container->getAccessLogHandler();

        $this->assertInstanceOf(AccessLogHandler::class, $accessLogHandler);
    }

    public function testGetErrorHandlerReturnsDefaultErrorHandlerInstance()
    {
        $container = new Container([]);

        $errorHandler = $container->getErrorHandler();

        $this->assertInstanceOf(ErrorHandler::class, $errorHandler);
    }

    public function testGetErrorHandlerReturnsErrorHandlerInstanceFromMap()
    {
        $errorHandler = new ErrorHandler();

        $container = new Container([
            ErrorHandler::class => $errorHandler
        ]);

        $ret = $container->getErrorHandler();

        $this->assertSame($errorHandler, $ret);
    }

    public function testGetErrorHandlerReturnsErrorHandlerInstanceFromPsrContainer()
    {
        $errorHandler = new ErrorHandler();

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(ErrorHandler::class)->willReturn(true);
        $psr->expects($this->once())->method('get')->with(ErrorHandler::class)->willReturn($errorHandler);

        $container = new Container($psr);

        $ret = $container->getErrorHandler();

        $this->assertSame($errorHandler, $ret);
    }

    public function testGetErrorHandlerReturnsDefaultErrorHandlerInstanceIfPsrContainerHasNoEntry()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(ErrorHandler::class)->willReturn(false);
        $psr->expects($this->never())->method('get');

        $container = new Container($psr);

        $errorHandler = $container->getErrorHandler();

        $this->assertInstanceOf(ErrorHandler::class, $errorHandler);
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

    public function testCtorWithInvalidValueThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($loader) must be of type array|Psr\Container\ContainerInterface, stdClass given');
        new Container((object) []);
    }
}
