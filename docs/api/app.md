# App

The `App` class is your main entrypoint to any application that builds on top of X.
It provides a simple API for routing HTTP requests as commonly used in RESTful applications.

Internally, the `App` object builds on top of [ReactPHP](https://reactphp.org/)
to do its magic, hence you have to create it like this:

```php
# app.php
<?php

require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new FrameworkX\App($loop);

// Register routes here, see routing…

$app->run();
$loop->run();
```

> ℹ️ **Heads up!**
>
> Major improvements upcoming! We're actively contributing to our underlying
> libraries to make sure this can look like this in the near future:
>
> ```php
> # app.php
> <?php
>
> require __DIR__ . '/vendor/autoload.php';
>
> $app = new FrameworkX\App();
>
> // Register routes here, see routing…
>
> $app->run();
> ```

## Routing

The `App` class offers a number of API methods that allow you to route incoming
HTTP requests to controller functions. In its most simple form, you can add
multiple routes using inline closures like this:

```php
$app->get('/user', function () {
    return new React\Http\Message\Response(200, [], "hello everybody!");
});

$app->get('/user/{id}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $id = $request->getAttribute('id');
    return new React\Http\Message\Response(200, [], "hello $id");
});
```

For example, an HTTP `GET` request for `/user` would call the first controller
function.
An HTTP `GET` request for `/user/alice` would call the second controller function
which also highlights how you can use [request attributes](request.md#attributes)
to access values from URI templates.

An HTTP `GET` request for `/foo` would automatically reject the HTTP request with
a 404 (Not Found) error response unless this route is registered.
Likewise, an HTTP `POST` request for `/user` would reject with a 405 (Method Not
Allowed) error response unless a route for this method is also registered.

You can route any number of incoming HTTP requests to controller functions by
using the matching API methods like this:

```php
$app->get('/user/{id}', $controller);
$app->head('/user/{id}', $controller);
$app->post('/user/{id}', $controller);
$app->put('/user/{id}', $controller);
$app->patch('/user/{id}', $controller);
$app->delete('/user/{id}', $controller);
$app->options('/user/{id}', $controller);
```

If you want to map multiple HTTP request methods to a single controller, you can
use this shortcut instead of listing each method explicitly like above:

```
$app->map(['GET', 'POST'], '/user/{id}', $controller);
```

If you want to map each and every HTTP request method to a single controller,
you can use this additional shortcut:

```
$app->any('/user/{id}', $controller);
```

## Controllers

The above examples use inline closures as controller functions to make these
examples more concise: 

```
$app->get('/', function () {
    return new React\Http\Message\Response(
        200,
        [],
        "Hello wörld!\n"
    );
});
```

While easy to get started, it's easy to see how this would become a mess once
you keep adding more controllers to a single application.
For this reason, we recommend using [controller classes](../best-practices/controllers.md)
for production use-cases like this:

```php
# app.php
$app->get('/', new Acme\Todo\HelloController());
```

```php
# src/HelloController.php
<?php

namespace Acme\Todo;

use React\Http\Message\Response;

class HelloController
{
    public function __invoke()
    {
        return new Response(
            200,
            [],
            "Hello wörld!\n"
        );
    }
}
```

See [controller classes](../best-practices/controllers.md) for more details.

## Middleware

One of the main features of the `App` is middleware support.
Middleware allows you to extract common functionality such as HTTP login, session handling or logging into reusable components.
These middleware components can be added to both individual routes or globally to all registered routes.
See [middleware documentation](middleware.md) for more details.
