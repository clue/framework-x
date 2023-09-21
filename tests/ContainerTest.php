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
    public function testCallableReturnsCallableForClassNameViaAutowiring(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class {
            public function __invoke(ServerRequestInterface $request): Response
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

    public function testCallableReturnsCallableForClassNameViaAutowiringWithConfigurationForDependency(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForNullableClassViaAutowiringWillDefaultToNullValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var ?\stdClass */
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForNullableClassViaContainerConfiguration(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var ?\stdClass */
            private $data;

            public function __construct(?\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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
    public function testCallableReturnsCallableForUnionWithNullViaAutowiringWillDefaultToNullValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        // @phpstan-ignore-next-line for PHP < 8
        $controller = new class(null) {
            /** @var mixed */
            private $data = false;

            #[PHP8] public function __construct(string|int|null $data) { $this->data = $data; } // @phpstan-ignore-line

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassWithNullDefaultViaAutowiringWillDefaultToNullValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            /** @var \stdClass|null|false */
            private $data = false;

            public function __construct(?\stdClass $data = null)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassWithNullDefaultViaContainerConfiguration(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            /** @var \stdClass|null|false */
            private $data = false;

            public function __construct(?\stdClass $data = null)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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
    public function testCallableReturnsCallableForUnionWithIntDefaultValueViaAutowiringWillDefaultToIntValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        // @phpstan-ignore-next-line for PHP < 8
        $controller = new class(null) {
            /** @var string|int|null|false */
            private $data = false;

            #[PHP8] public function __construct(string|int|null $data = 42) { $this->data = $data; } // @phpstan-ignore-line

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForUntypedWithStringDefaultViaAutowiringWillDefaultToStringValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(null) {
            /** @var mixed */
            private $data = false;

            /** @param mixed $data */
            public function __construct($data = 'empty')
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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
    public function testCallableReturnsCallableForMixedWithStringDefaultViaAutowiringWillDefaultToStringValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        // @phpstan-ignore-next-line for PHP < 8
        $controller = new class(null) {
            /** @var mixed */
            private $data = false;

            #[PHP8] public function __construct(mixed $data = 'empty') { $this->data = $data; } // @phpstan-ignore-line

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameViaAutowiringWithFactoryFunctionForDependency(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableTwiceReturnsCallableForClassNameViaAutowiringWithFactoryFunctionForDependencyWillCallFactoryOnlyOnce(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedToSubclassExplicitly(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $dto = new class extends \stdClass { };
        $dto->name = 'Alice';

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedToSubclassFromFactory(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $dto = new class extends \stdClass { };
        $dto->name = 'Alice';

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresOtherClassWithFactory(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (\stdClass $dto) {
                return new Response(200, [], (string) json_encode($dto));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (\stdClass $data) {
                return new Response(200, [], (string) json_encode($data));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariableWithFactory(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (\stdClass $data) {
                return new Response(200, [], (string) json_encode($data));
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

    /** @return list<list<\stdClass|string|null>> */
    public function provideMixedValue(): array
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
     * @param \stdClass|string|null $value
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariable($value, string $json): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function ($data) {
                return new Response(200, [], (string) json_encode($data));
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
     * @param \stdClass|string|null $value
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariableWithFactory($value, string $json): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function ($data) {
                return new Response(200, [], (string) json_encode($data));
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
     * @param \stdClass|string|null $value
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedContainerVariable($value, string $json): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (mixed $data) {
                return new Response(200, [], (string) json_encode($data));
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
     * @param mixed $value
     */
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedContainerVariableWithFactory($value, string $json): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (mixed $data) {
                return new Response(200, [], (string) json_encode($data));
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

    public function testCallableReturnsCallableForClassWithDependencyMappedWithFactoryThatRequiresUntypedContainerVariableWithIntDefaultAssignExplicitNullValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function ($data = 42) {
                return new Response(200, [], (string) json_encode($data));
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
    public function testCallableReturnsCallableForClassWithDependencyMappedWithFactoryThatRequiresMixedContainerVariableWithIntDefaultAssignExplicitNullValue(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $fn = $data = null;
        $fn = #[PHP8] fn(mixed $data = 42) => new Response(200, [], (string) json_encode($data)); // @phpstan-ignore-line
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableContainerVariables(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (?\stdClass $user, ?\stdClass $data) {
                return new Response(200, [], (string) json_encode(['user' => $user, 'data' => $data]));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableContainerVariablesWithFactory(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (?\stdClass $user, ?\stdClass $data) {
                return new Response(200, [], (string) json_encode(['user' => $user, 'data' => $data]));
            },
            'user' => function (): ?\stdClass { // @phpstan-ignore-line
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresContainerVariablesWithDefaultValues(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (string $name = 'Alice', int $age = 0) {
                return new Response(200, [], (string) json_encode(['name' => $name, 'age' => $age]));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresScalarVariables(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameMappedFromFactoryWithScalarVariablesMappedFromFactory(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameReferencingVariableMappedFromFactoryReferencingVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            /** @var \stdClass */
            private $data;

            public function __construct(\stdClass $data)
            {
                $this->data = $data;
            }

            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200, [], (string) json_encode($this->data));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresStringEnvironmentVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (string $FOO) {
                return new Response(200, [], (string) json_encode($FOO));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresStringMappedFromFactoryThatRequiresStringEnvironmentVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (string $address) {
                return new Response(200, [], (string) json_encode($address));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableStringEnvironmentVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (?string $FOO) {
                return new Response(200, [], (string) json_encode($FOO));
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

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresNullableStringEnvironmentVariableAssignsNull(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (?string $FOO) {
                return new Response(200, [], (string) json_encode($FOO));
            }
        ]);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('null', (string) $response->getBody());
    }

    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresUntypedEnvironmentVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function ($FOO) {
                return new Response(200, [], (string) json_encode($FOO));
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
    public function testCallableReturnsCallableForClassNameWithDependencyMappedWithFactoryThatRequiresMixedEnvironmentVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new Response()) {
            /** @var ResponseInterface */
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function __invoke(): ResponseInterface
            {
                return $this->response;
            }
        };

        $container = new Container([
            ResponseInterface::class => function (mixed $FOO) {
                return new Response(200, [], (string) json_encode($FOO));
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesUnknownVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
            }
        };

        $line = __LINE__ + 2;
        $container = new Container([
            \stdClass::class => function (string $username) {
                return (object) ['name' => $username];
            }
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($username) of {closure:' . __FILE__ . ':' . $line .'}() is not defined');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesRecursiveVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesStringVariableMappedWithUnexpectedObjectType(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class('') {
            public function __construct(string $stdClass)
            {
                assert(is_string($stdClass));
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesVariableMappedFromFactoryWithUnexpectedReturnType(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesObjectVariableMappedFromFactoryWithReturnsUnexpectedInteger(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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
        $this->expectExceptionMessage('Container variable $http expected type stdClass, but got int');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesStringVariableMappedFromFactoryWithReturnsUnexpectedInteger(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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
        $this->expectExceptionMessage('Container variable $http expected type string, but got int');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesIntVariableMappedFromFactoryWithReturnsUnexpectedString(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesFloatVariableMappedFromFactoryWithReturnsUnexpectedString(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesBoolVariableMappedFromFactoryWithReturnsUnexpectedString(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsStringVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesNullableClassButGetsStringVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(?\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsIntVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
            }
        };

        $container = new Container([
            \stdClass::class => 42
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected int');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassNameButGetsNullVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
            }
        };

        $container = new Container([
            \stdClass::class => null
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected null');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesNullableClassNameButGetsNullVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(?\stdClass $data)
            {
                assert($data instanceof \stdClass);
            }
        };

        $container = new Container([
            \stdClass::class => null
        ]);

        $callable = $container->callable(get_class($controller));

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for stdClass contains unexpected null');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReferencesClassMappedToUnexpectedObject(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class(new \stdClass()) {
            public function __construct(\stdClass $data)
            {
                assert($data instanceof \stdClass);
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

    public function testCallableReturnsCallableThatThrowsWhenConstructorWithoutFactoryFunctionReferencesStringVariable(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class('Alice') {
            public function __construct(string $name)
            {
                assert(is_string($name));
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

    public function testCtorThrowsWhenMapContainsInvalidArray(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for all contains unexpected array');

        new Container([ // @phpstan-ignore-line
            'all' => []
        ]);
    }

    public function testCtorThrowsWhenMapContainsInvalidResource(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for file contains unexpected resource');

        new Container([ // @phpstan-ignore-line
            'file' => tmpfile()
        ]);
    }

    public function testCtorThrowsWhenMapForClassContainsInvalidObject(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for Psr\Http\Message\ResponseInterface contains unexpected stdClass');

        new Container([
            ResponseInterface::class => new \stdClass()
        ]);
    }

    public function testCtorThrowsWhenMapForClassContainsInvalidNull(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Map for Psr\Http\Message\ResponseInterface contains unexpected null');

        new Container([
            ResponseInterface::class => null
        ]);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidClassName(): void
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidInteger(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container([
            \stdClass::class => function () { return 42; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Factory for stdClass returned unexpected int');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenMapReferencesClassNameThatDoesNotMatchType(): void
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsClassNameThatDoesNotMatchType(): void
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresInvalidClassName(): void
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresUntypedArgument(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $line = __LINE__ + 2;
        $container = new Container([
            \stdClass::class => function ($undefined) { return $undefined; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($undefined) of {closure:' . __FILE__ . ':' . $line .'}() has no type');
        $callable($request);
    }

    /**
     * @requires PHP 8
     */
    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresUndefinedMixedArgument(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $line = __LINE__ + 2;
        $container = new Container([
            \stdClass::class => function (mixed $undefined) { return $undefined; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($undefined) of {closure:' . __FILE__ . ':' . $line .'}() is not defined');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryRequiresRecursiveClass(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $line = __LINE__ + 2;
        $container = new Container([
            \stdClass::class => function (\stdClass $data) { return $data; }
        ]);

        $callable = $container->callable(\stdClass::class);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($data) of {closure:' . __FILE__ . ':' . $line .'}() is recursive');
        $callable($request);
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryIsRecursive(): void
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

    public function testCallableReturnsCallableThatThrowsWhenFactoryIsRecursiveClassName(): void
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

    public function testCallableReturnsCallableForClassNameViaPsrContainer(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $controller = new class {
            public function __invoke(ServerRequestInterface $request): Response
            {
                return new Response(200);
            }
        };

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->never())->method('has');
        $psr->expects($this->once())->method('get')->with(get_class($controller))->willReturn($controller);

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $callable = $container->callable(get_class($controller));
        $this->assertInstanceOf(\Closure::class, $callable);

        $response = $callable($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCallableReturnsCallableThatThrowsWhenFactoryReturnsInvalidClassNameViaPsrContainer(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $exception = new class('Unable to load class') extends \RuntimeException implements NotFoundExceptionInterface { };

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->never())->method('has');
        $psr->expects($this->once())->method('get')->with('FooBar')->willThrowException($exception);

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $callable = $container->callable('FooBar'); // @phpstan-ignore-line

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Request handler class FooBar failed to load: Unable to load class');
        $callable($request);
    }

    public function testGetEnvReturnsNullWhenEnvironmentDoesNotExist(): void
    {
        $container = new Container([]);

        $this->assertNull($container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromMap(): void
    {
        $container = new Container([
            'X_FOO' => 'bar'
        ]);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromMapFactory(): void
    {
        $container = new Container([
            'X_FOO' => function (string $bar) { return $bar; },
            'bar' => 'bar'
        ]);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromGlobalEnvIfNotSetInMap(): void
    {
        $container = new Container([]);

        $_ENV['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalServerIfNotSetInMap(): void
    {
        $container = new Container([]);

        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_SERVER['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromProcessEnvIfNotSetInMap(): void
    {
        $container = new Container([]);

        putenv('X_FOO=bar');
        $ret = $container->getEnv('X_FOO');
        putenv('X_FOO');

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalEnvBeforeServerIfNotSetInMap(): void
    {
        $container = new Container([]);

        $_ENV['X_FOO'] = 'foo';
        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO'], $_SERVER['X_FOO']);

        $this->assertEquals('foo', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalEnvBeforeProcessEnvIfNotSetInMap(): void
    {
        $container = new Container([]);

        $_ENV['X_FOO'] = 'foo';
        putenv('X_FOO=bar');
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO']);
        putenv('X_FOO');

        $this->assertEquals('foo', $ret);
    }

    public function testGetEnvReturnsStringFromPsrContainer(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(true);
        $psr->expects($this->once())->method('get')->with('X_FOO')->willReturn('bar');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $this->assertEquals('bar', $container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsNullIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $this->assertNull($container->getEnv('X_FOO'));
    }

    public function testGetEnvReturnsStringFromProcessEnvIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->atLeastOnce())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        putenv('X_FOO=bar');
        $ret = $container->getEnv('X_FOO');
        putenv('X_FOO');

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalEnvIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->atLeastOnce())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $_ENV['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalServerIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->atLeastOnce())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_SERVER['X_FOO']);

        $this->assertEquals('bar', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalEnvBeforeServerIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->atLeastOnce())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $_ENV['X_FOO'] = 'foo';
        $_SERVER['X_FOO'] = 'bar';
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO'], $_SERVER['X_FOO']);

        $this->assertEquals('foo', $ret);
    }

    public function testGetEnvReturnsStringFromGlobalEnvBeforeProcessEnvIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->atLeastOnce())->method('has')->with('X_FOO')->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $_ENV['X_FOO'] = 'foo';
        putenv('X_FOO=bar');
        $ret = $container->getEnv('X_FOO');
        unset($_ENV['X_FOO']);
        putenv('X_FOO');

        $this->assertEquals('foo', $ret);
    }

    public function testGetEnvThrowsIfMapContainsInvalidType(): void
    {
        $container = new Container([
            'X_FOO' => false
        ]);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Environment variable $X_FOO expected type string|null, but got false');
        $container->getEnv('X_FOO');
    }

    /**
     * @requires PHP 8
     */
    public function testGetEnvThrowsWhenFactoryUsesBuiltInFunctionThatReferencesUnknownVariable(): void
    {
        $container = new Container([
            'X_FOO' => \Closure::fromCallable('extension_loaded')
        ]);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($extension) of extension_loaded() is not defined');
        $container->getEnv('X_FOO');
    }

    public function testGetEnvThrowsWhenFactoryUsesClassMethodThatReferencesUnknownVariable(): void
    {
        $class = new class {
            public function foo(string $bar): string
            {
                return $bar;
            }
        };

        $container = new Container([
            'X_FOO' => \Closure::fromCallable([$class, 'foo'])
        ]);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Argument 1 ($bar) of class@anonymous::foo() is not defined');
        $container->getEnv('X_FOO');
    }

    public function testGetEnvThrowsIfMapPsrContainerReturnsInvalidType(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with('X_FOO')->willReturn(true);
        $psr->expects($this->once())->method('get')->with('X_FOO')->willReturn(42);

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Environment variable $X_FOO expected type string|null, but got int');
        $container->getEnv('X_FOO');
    }

    public function testGetAccessLogHandlerReturnsDefaultAccessLogHandlerInstance(): void
    {
        $container = new Container([]);

        $accessLogHandler = $container->getAccessLogHandler();

        $this->assertInstanceOf(AccessLogHandler::class, $accessLogHandler);
    }

    public function testGetAccessLogHandlerReturnsAccessLogHandlerInstanceFromMap(): void
    {
        $accessLogHandler = new AccessLogHandler();

        $container = new Container([
            AccessLogHandler::class => $accessLogHandler
        ]);

        $ret = $container->getAccessLogHandler();

        $this->assertSame($accessLogHandler, $ret);
    }

    public function testGetAccessLogHandlerReturnsAccessLogHandlerInstanceFromPsrContainer(): void
    {
        $accessLogHandler = new AccessLogHandler();

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(AccessLogHandler::class)->willReturn(true);
        $psr->expects($this->once())->method('get')->with(AccessLogHandler::class)->willReturn($accessLogHandler);

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $ret = $container->getAccessLogHandler();

        $this->assertSame($accessLogHandler, $ret);
    }

    public function testGetAccessLogHandlerReturnsDefaultAccessLogHandlerInstanceIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(AccessLogHandler::class)->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $accessLogHandler = $container->getAccessLogHandler();

        $this->assertInstanceOf(AccessLogHandler::class, $accessLogHandler);
    }

    public function testGetErrorHandlerReturnsDefaultErrorHandlerInstance(): void
    {
        $container = new Container([]);

        $errorHandler = $container->getErrorHandler();

        $this->assertInstanceOf(ErrorHandler::class, $errorHandler);
    }

    public function testGetErrorHandlerReturnsErrorHandlerInstanceFromMap(): void
    {
        $errorHandler = new ErrorHandler();

        $container = new Container([
            ErrorHandler::class => $errorHandler
        ]);

        $ret = $container->getErrorHandler();

        $this->assertSame($errorHandler, $ret);
    }

    public function testGetErrorHandlerReturnsErrorHandlerInstanceFromPsrContainer(): void
    {
        $errorHandler = new ErrorHandler();

        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(ErrorHandler::class)->willReturn(true);
        $psr->expects($this->once())->method('get')->with(ErrorHandler::class)->willReturn($errorHandler);

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $ret = $container->getErrorHandler();

        $this->assertSame($errorHandler, $ret);
    }

    public function testGetErrorHandlerReturnsDefaultErrorHandlerInstanceIfPsrContainerHasNoEntry(): void
    {
        $psr = $this->createMock(ContainerInterface::class);
        $psr->expects($this->once())->method('has')->with(ErrorHandler::class)->willReturn(false);
        $psr->expects($this->never())->method('get');

        assert($psr instanceof ContainerInterface);
        $container = new Container($psr);

        $errorHandler = $container->getErrorHandler();

        $this->assertInstanceOf(ErrorHandler::class, $errorHandler);
    }

    public function testInvokeContainerAsMiddlewareReturnsFromNextRequestHandler(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');
        $response = new Response(200, [], '');

        $container = new Container();
        $ret = $container($request, function () use ($response) { return $response; });

        $this->assertSame($response, $ret);
    }

    public function testInvokeContainerAsFinalRequestHandlerThrows(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/');

        $container = new Container();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Container should not be used as final request handler');
        $container($request);
    }

    public static function provideInvalidContainerConfigValues(): \Generator
    {
        yield [
            (object) [],
            \stdClass::class
        ];
        yield [
            new Container([]),
            Container::class
        ];
        yield [
            true,
            'true'
        ];
        yield [
            false,
            'false'
        ];
        yield [
            1.0,
            'float'
        ];
    }

    /**
     * @dataProvider provideInvalidContainerConfigValues
     * @param mixed $value
     * @param string $type
     */
    public function testCtorWithInvalidValueThrows($value, string $type): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #1 ($loader) must be of type array|Psr\Container\ContainerInterface, ' . $type . ' given');
        new Container($value); // @phpstan-ignore-line
    }
}
