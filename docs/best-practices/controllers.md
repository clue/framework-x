# Controller classes

## First steps

When starting with X, it's often easiest to start with simple closure definitions like suggested in the [quickstart guide](../getting-started/quickstart.md).

As a next step, let's take a look at how this structure can be improved with controller classes.
This is especially useful once you leave the prototyping phase and want to find the best structure for a production-ready setup.

To get started, let's take a look at the following simple closure definitions:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

$app->get('/', function () {
    return React\Http\Message\Response::plaintext(
        "Hello wörld!\n"
    );
});

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    return React\Http\Message\Response::plaintext(
        "Hello " . $request->getAttribute('name') . "!\n"
    );
});

$app->run();
```

While easy to get started, it's also easy to see how this will get out of hand for more complex
business domains when you have more than a couple of routes registered.

For real-world applications, we highly recommend structuring your application
into individual controller classes. This way, we can break up the above
definition into three even simpler files:

=== "Using controller instances"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App();

    $app->get('/', new Acme\Todo\HelloController());
    $app->get('/users/{name}', new Acme\Todo\UserController());

    $app->run();
    ```

=== "Using controller names"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App();

    $app->get('/', Acme\Todo\HelloController::class);
    $app->get('/users/{name}', Acme\Todo\UserController::class);

    $app->run();
    ```

<!-- -->

```php title="src/HelloController.php"
<?php

namespace Acme\Todo;

use React\Http\Message\Response;

class HelloController
{
    public function __invoke()
    {
        return Response::plaintext(
            "Hello wörld!\n"
        );
    }
}
```

```php title="src/UserController.php"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class UserController
{
    public function __invoke(ServerRequestInterface $request)
    {
        return Response::plaintext(
            "Hello " . $request->getAttribute('name') . "!\n"
        );
    }
}
```

## Composer autoloading

Doesn't look too complex, right? Now, we only need to tell Composer's autoloader
about our vendor namespace `Acme\Todo` in the `src/` folder. Make sure to include
the following lines in your `composer.json` file:

```json title="composer.json"
{
    "autoload": {
        "psr-4": {
            "Acme\\Todo\\": "src/"
        }
    }
}
```

When we're doing this the first time, we have to update Composer's generated
autoloader classes:

```bash
$ composer dump-autoload
```

> ℹ️ **New to Composer?**
>
> Don't worry, that's a one-time setup only. If you're used to working with
Composer, this shouldn't be too surprising. If this sounds new to you, rest
assured this is the only time you have to worry about this, new classes can
simply be added without having to run Composer again.

Again, let's see our web application still works by using your favorite
web browser or command-line tool:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

If everything works as expected, we can continue with writing our first tests to automate this.

## Container

X has a powerful, built-in dependency injection container (DI container or DIC).
It allows you to automatically create request handler classes and their
dependencies with zero configuration for most common use cases.

> ℹ️ **Dependency Injection (DI)**
>
> Dependency injection (DI) is a technique in which an object receives other
> objects that it depends on, rather than creating these dependencies within its
> class. In its most basic form, this means creating all required object
> dependencies upfront and manually injecting them into the controller class.
> This can be done manually or you can use the optional container which does
> this for you.

### Autowiring

To use autowiring, simply pass in the class name of your request handler classes
like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

$app->get('/', Acme\Todo\HelloController::class);
$app->get('/users/{name}', Acme\Todo\UserController::class);

$app->run();
```

X will automatically take care of instantiating the required request handler
classes and their dependencies when a request comes in. This autowiring feature
covers most common use cases:

* Names always reference existing class names.
* Class names need to be loadable through the autoloader. See
  [composer autoloading](#composer-autoloading) above.
* Each class may or may not have a constructor.
* If the constructor has an optional argument, it will be omitted.
* If the constructor has a nullable argument, it will be given a `null` value.
* If the constructor references another class, it will load this class next.

This covers most common use cases where the request handler class uses a
constructor with type definitions to explicitly reference other classes.

### Container configuration

Autowiring should cover most common use cases with zero configuration. If you
want to have more control over this behavior, you may also explicitly configure
the dependency injection container like this:

=== "Arrow functions (PHP 7.4+)" 

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $container = new FrameworkX\Container([
        Acme\Todo\HelloController::class => fn() => new Acme\Todo\HelloController()
    ]);



    $app = new FrameworkX\App($container);

    $app->get('/', Acme\Todo\HelloController::class);
    $app->get('/users/{name}', Acme\Todo\UserController::class);

    $app->run();
    ```

=== "Closure" 

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $container = new FrameworkX\Container([
        Acme\Todo\HelloController::class => function () {
            return new Acme\Todo\HelloController();
        }
    ]);

    $app = new FrameworkX\App($container);

    $app->get('/', Acme\Todo\HelloController::class);
    $app->get('/users/{name}', Acme\Todo\UserController::class);

    $app->run();
    ```

This can be useful in these cases:

* Constructor parameter references an interface and you want to explicitly
  define an instance that implements this interface.
* Constructor parameter has a primitive type (scalars such as `int` or `string`
  etc.) or has no type at all and you want to explicitly bind a given value.
* Constructor parameter references a class, but you want to inject a specific
  instance or subclass in place of a default class.

The configured container instance can be passed into the application like any
other middleware request handler. In most cases this means you create a single
`Container` instance with a number of factory functions and pass this instance as
the first argument to the `App`.

In its most common form, each entry in the container configuration maps a class
name to a factory function that will be invoked when this class is first
requested. The factory function is responsible for returning an instance that
implements the given class name.

Factory functions used in the container configuration map may reference other
classes that will automatically be injected from the container. This can be
particularly useful when combining autowiring with some manual configuration
like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$container = new FrameworkX\Container([
    Acme\Todo\UserController::class => function (React\Http\Browser $browser) {
        // example UserController class requires two arguments:
        // - first argument will be autowired based on class reference
        // - second argument expects some manual value
        return new Acme\Todo\UserController($browser, 42);
    }
]);

// …
```

Factory functions used in the container configuration map may also reference
string variables defined in the container configuration. This can be
particularly useful when combining autowiring with some manual configuration
like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$container = new FrameworkX\Container([
    Acme\Todo\UserController::class => function (string $name) {
        // example UserController class requires single string argument
        return new Acme\Todo\UserController($name);
    },
    'name' => 'Acme'
]);

// …
```

> ℹ️ **Avoiding name conflicts**
>
> Note that class names and string variables share the same container
> configuration map and as such might be subject to name collisions as a single
> entry may only have a single value. For this reason, container variables will
> only be used for container functions by default. We highly recommend using
> namespaced class names like in the previous example. You may also want to make
> sure that container variables use unique names prefixed with your vendor name.

The container configuration may also be used to map a class name to a different
class name that implements the same interface, either by mapping between two
class names or using a factory function that returns a class name. This is
particularly useful when implementing an interface.

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$container = new FrameworkX\Container([
    React\Cache\CacheInterface::class => React\Cache\ArrayCache::class,
    Psr\Http\Message\ResponseInterface::class => function () {
        // returns class implementing interface from factory function
        return React\Http\Message\Response::class;
    }
]);

// …
```

### PSR-11: Container interface

X has a powerful, built-in dependency injection container (DI container or DIC)
that has a strong focus on simplicity and should cover most common use cases.
Sometimes, you might need a little more control over this and may want to use a
custom container implementation instead.

We love standards and interoperability, that's why we support the
[PSR-11: Container interface](https://www.php-fig.org/psr/psr-11/). This is a
common interface that is used by most DI containers in PHP. In the following
example, we're using [PHP-DI](https://php-di.org/), but you may likewise use any
other implementation of this interface:

```bash
composer require php-di/php-di
```

In order to use an external DI container, you first have to instantiate your
custom container as per its documentation. If this instance implements the
`Psr\Container\ContainerInterface`, you can then pass it into the X container that
acts as an adapter for the application like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

// $builder = new DI\ContainerBuilder();
// $builder->...
// $container = $builder->build();
$container = new DI\Container();

$app = new FrameworkX\App(new FrameworkX\Container($container));

$app->get('/', Acme\Todo\HelloController::class);
$app->get('/users/{name}', Acme\Todo\UserController::class);

$app->run();
```

We expect most applications to work just fine with the built-in DI container.
If you need to use a custom container, the above logic should work with any of the
[PSR-11 container implementations](https://packagist.org/providers/psr/container-implementation).
