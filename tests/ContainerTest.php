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

    public function testCallableReturnsCallableForNullableClassViaAutowiringWillDefaultToNullValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForNullableClassViaContainerConfiguration()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => (object) []
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{}', (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableForUnionWithNullViaAutowiringWillDefaultToNullValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            #[PHP8] public function __construct(string|int|null $data) { $this->data = $data; }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassWithNullDefaultViaAutowiringWillDefaultToNullValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            public function __construct(\stdClass $data = null)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassWithNullDefaultViaContainerConfiguration()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            public function __construct(\stdClass $data = null)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => (object) []
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{}', (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableForUnionWithIntDefaultValueViaAutowiringWillDefaultToIntValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            #[PHP8] public function __construct(string|int|null $data = 42) { $this->data = $data; }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('42', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForUntypedWithStringDefaultViaAutowiringWillDefaultToStringValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            public function __construct($data = 'empty')
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"empty"', (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableForMixedWithStringDefaultViaAutowiringWillDefaultToStringValue()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            private $data = false;

            #[PHP8] public function __construct(mixed $data = 'empty') { $this->data = $data; }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"empty"', (string) $response->getBody());
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedToSubclassExplicitly()
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedToSubclassFromFactory()
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresOtherClassWithFactory()
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariable()
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
            ResponseInterface::class => function (\stdClass $data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => (object) ['name' => 'Alice']
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariableWithFactory()
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
            ResponseInterface::class => function (\stdClass $data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => function () {
                return (object) ['name' => 'Alice'];
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice"}', (string) $response->getBody());
    }

    public function provideMixedValue()
    {
        return [
            [
                (object) ['name' => 'Alice'],
                '{"name":"Alice"}'
            ],
            [
                'Alice',
                '"Alice"'
            ],
            [
                null,
                'null'
            ]
        ];
    }

    /**
     * @dataProvider provideMixedValue
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariable($value, string $json)
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
            ResponseInterface::class => function ($data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => $value
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($json, (string) $response->getBody());
    }

    /**
     * @dataProvider provideMixedValue
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariableWithFactory($value, string $json)
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
            ResponseInterface::class => function ($data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => function () use ($value) {
                return $value;
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($json, (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     * @dataProvider provideMixedValue
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedContainerVariable($value, string $json)
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
            ResponseInterface::class => function (mixed $data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => $value
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($json, (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     * @dataProvider provideMixedValue
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedContainerVariableWithFactory($value, string $json)
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
            ResponseInterface::class => function (mixed $data) {
                return new Response(200, [], json_encode($data));
            },
            'data' => function () use ($value) {
                return $value;
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($json, (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariableWithIntDefaultAssignExplicitNullValue()
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
            ResponseInterface::class => function ($data = 42) {
                return new Response(200, [], json_encode($data));
            },
            'data' => null
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableForClassWithDependencyMappedWithFactoryThatRequiresMixedContainerVariableWithIntDefaultAssignExplicitNullValue()
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

        $fn = #[PHP8] fn(mixed $data = 42) => new Response(200, [], json_encode($data));
        $container = new Container([
            ResponseInterface::class => $fn,
            'data' => null
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableContainerVariables()
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
            ResponseInterface::class => function (?\stdClass $user, ?\stdClass $data) {
                return new Response(200, [], json_encode(['user' => $user, 'data' => $data]));
            },
            'user' => (object) []
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"user":{},"data":null}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableContainerVariablesWithFactory()
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
            ResponseInterface::class => function (?\stdClass $user, ?\stdClass $data) {
                return new Response(200, [], json_encode(['user' => $user, 'data' => $data]));
            },
            'user' => function (): ?\stdClass {
                return (object) [];
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"user":{},"data":null}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariablesWithDefaultValues()
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
            ResponseInterface::class => function (string $name = 'Alice', int $age = 0) {
                return new Response(200, [], json_encode(['name' => $name, 'age' => $age]));
            },
            'age' => 42
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice","age":42}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresScalarVariables()
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
            \stdClass::class => function (string $username, int $age, bool $admin, float $percent) {
                return (object) ['name' => $username, 'age' => $age, 'admin' => $admin, 'percent' => $percent];
            },
            'username' => 'Alice',
            'age' => 42,
            'admin' => true,
            'percent' => 0.5
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice","age":42,"admin":true,"percent":0.5}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameMappedFromFactoryWithScalarVariablesMappedFromFactory()
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
            \stdClass::class => function (string $username, int $age, bool $admin, float $percent) {
                return (object) ['name' => $username, 'age' => $age, 'admin' => $admin, 'percent' => $percent];
            },
            'username' => function () { return 'Alice'; },
            'age' => function () { return 42; },
            'admin' => function () { return true; },
            'percent' => function () { return 0.5; }
        ]);

        $callable = $container->callable(get_class($controller));

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"Alice","age":42,"admin":true,"percent":0.5}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameReferencingVariableMappedFromFactoryReferencingVariable()
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
            \stdClass::class => function (string $username) {
                return (object) ['name' => $username];
            },
            'username' => function (string $role) {
                return strtoupper($role);
            },
            'role' => 'admin'
        ]);

        $callable = $container->callable(get_class($controller));

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"name":"ADMIN"}', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresStringEnvironmentVariable()
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
            ResponseInterface::class => function (string $FOO) {
                return new Response(200, [], json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $_SERVER['FOO'] = 'bar';
        $response = $callable($request);
        unset($_SERVER['FOO']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"bar"', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresStringMappedFromFactoryThatRequiresStringEnvironmentVariable()
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
            ResponseInterface::class => function (string $address) {
                return new Response(200, [], json_encode($address));
            },
            'address' => function (string $FOO) {
                return 'http://' . $FOO;
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $_SERVER['FOO'] = 'bar';
        $response = $callable($request);
        unset($_SERVER['FOO']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"http:\/\/bar"', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableStringEnvironmentVariable()
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
            ResponseInterface::class => function (?string $FOO) {
                return new Response(200, [], json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $_SERVER['FOO'] = 'bar';
        $response = $callable($request);
        unset($_SERVER['FOO']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"bar"', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableStringEnvironmentVariableAssignsNull()
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
            ResponseInterface::class => function (?string $FOO) {
                return new Response(200, [], json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedEnvironmentVariable()
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
            ResponseInterface::class => function ($FOO) {
                return new Response(200, [], json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $_SERVER['FOO'] = 'bar';
        $response = $callable($request);
        unset($_SERVER['FOO']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"bar"', (string) $response->getBody());
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedEnvironmentVariable()
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
            ResponseInterface::class => function (mixed $FOO) {
                return new Response(200, [], json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $_SERVER['FOO'] = 'bar';
        $response = $callable($request);
        unset($_SERVER['FOO']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('"bar"', (string) $response->getBody());
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesUnknownVariable()
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
            \stdClass::class => function (string $username) {
                return (object) ['name' => $username];
            }
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($username) of {closure}() is not defined');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesRecursiveVariable()
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
            \stdClass::class => function (string $stdClass) {
                return (object) ['name' => $stdClass];
            }
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $stdClass is recursive');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesStringVariableMappedWithUnexpectedObjectType()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class('') {
            private $data;

            public function __construct(string $stdClass)
            {
                $this->data = $stdClass;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            get_class($controller) => function (string $stdClass) use ($controller) {
                $class = get_class($controller);
                return new $class($stdClass);
            },
            \stdClass::class => (object) []
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $stdClass expected type string, but got stdClass');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesVariableMappedFromFactoryWithUnexpectedReturnType()
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
            \stdClass::class => function (string $http) {
                return (object) ['name' => $http];
            },
            'http' => function () {
                return tmpfile();
            }
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $http expected type object|scalar|null from factory, but got resource');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesObjectVariableMappedFromFactoryWithReturnsUnexpectedInteger()
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
            \stdClass::class => function (\stdClass $http) {
                return (object) ['name' => $http];
            },
            'http' => 1
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $http expected type stdClass, but got integer');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesStringVariableMappedFromFactoryWithReturnsUnexpectedInteger()
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
            \stdClass::class => function (string $http) {
                return (object) ['name' => $http];
            },
            'http' => 1
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $http expected type string, but got integer');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesIntVariableMappedFromFactoryWithReturnsUnexpectedString()
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
            \stdClass::class => function (int $http) {
                return (object) ['name' => $http];
            },
            'http' => '1.1'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $http expected type int, but got string');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesFloatVariableMappedFromFactoryWithReturnsUnexpectedString()
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
            \stdClass::class => function (float $percent) {
                return (object) ['percent' => $percent];
            },
            'percent' => '100%'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $percent expected type float, but got string');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesBoolVariableMappedFromFactoryWithReturnsUnexpectedString()
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
            \stdClass::class => function (bool $admin) {
                return (object) ['admin' => $admin];
            },
            'admin' => 'Yes'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container variable $admin expected type bool, but got string');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsStringVariable()
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
            \stdClass::class => 'Yes'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Class Yes not found');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesNullableClassButGetsStringVariable()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => 'Yes'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Class Yes not found');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsIntVariable()
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
            \stdClass::class => 42
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected integer');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsNullVariable()
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
            \stdClass::class => null
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected NULL');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesNullableClassNameButGetsNullVariable()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            \stdClass::class => null
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected NULL');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassMappedToUnexpectedObject()
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
            \stdClass::class => new Response()
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected React\Http\Message\Response');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenConstructorWithoutFactoryFunctionReferencesStringVariable()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class('Alice') {
            private $data;

            public function __construct(string $name)
            {
                $this->data = $name;
            }

            public function __invoke(ServerRequestInterface $request)
            {
                return new Response(200, [], json_encode($this->data));
            }
        };

        $container = new Container([
            'name' => 'Alice'
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($name) of class@anonymous::__construct() expects unsupported type string');
        $callable($request);
    }

    public function testCtorThrowsWhenMapContainsInvalidArray()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for all contains unexpected array');

        new Container([
            'all' => []
        ]);
    }

    public function testCtorThrowsWhenMapContainsInvalidResource()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for file contains unexpected resource');

        new Container([
            'file' => tmpfile()
        ]);
    }

    public function testCtorThrowsWhenMapForClassContainsInvalidObject()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for Psr\Http\Message\ResponseInterface contains unexpected stdClass');

        new Container([
            ResponseInterface::class => new \stdClass()
        ]);
    }

    public function testCtorThrowsWhenMapForClassContainsInvalidNull()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for Psr\Http\Message\ResponseInterface contains unexpected NULL');

        new Container([
            ResponseInterface::class => null
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

    public function testCallableReturnsCallableThatThrowsWhenMapReferencesClassNameThatDoesNotMatchType()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => Response::class
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Factory for stdClass returned unexpected React\Http\Message\Response');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsClassNameThatDoesNotMatchType()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function () { return Response::class; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Factory for stdClass returned unexpected React\Http\Message\Response');
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
            \stdClass::class => function ($undefined) { return $undefined; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($undefined) of {closure}() has no type');
        $callable($request);
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresUndefinedMixedArgument()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function (mixed $undefined) { return $undefined; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($undefined) of {closure}() is not defined');
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryIsRecursiveClassName()
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function (): string {
                return \stdClass::class;
            }
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

    public function testGetEnvReturnsNullWhenEnvironmentDoesNotExist()
    {
        $container = new Container([]);

        $this->assertNull($container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromMap()
    {
        $container = new Container([
            'X_FOO' => 'bar'
        ]);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromMapFactory()
    {
        $container = new Container([
            'X_FOO' => function (string $bar) { return $bar; },
            'bar' => 'bar'
        ]);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromGlobalServerIfNotSetInMap()
    {
        $container = new Container([]);

        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_SERVER['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromPsrContainer()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(true);
        $psr->expects($this->once())->method('get')->with('X_FOO')->willReturn('bar');

        $container = new Container($psr);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsNullIfPsrContainerHasNoEntry()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        $container = new Container($psr);

        $this->assertNull($container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromGlobalServerIfPsrContainerHasNoEntry()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        $container = new Container($psr);

        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_SERVER['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvThrowsIfMapContainsInvalidType()
    {
        $container = new Container([
            'X_FOO' => false
        ]);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Environment variable $X_FOO expected type string|null, but got boolean');
        $container->getEnv('X_FOO');
    }

    public function testGetEnvThrowsIfMapPsrContainerReturnsInvalidType()
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(true);
        $psr->expects($this->once())->method('get')->with('X_FOO')->willReturn(42);

        $container = new Container($psr);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Environment variable $X_FOO expected type string|null, but got integer');
        $container->getEnv('X_FOO');
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
