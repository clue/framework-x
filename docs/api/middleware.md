# Middleware

> ℹ️ **Feature preview**
>
> This is a feature preview, i.e. it might not have made it into the current beta.
> Give feedback to help us prioritize.

One of the main features of X is middleware support.
Middleware allows you to extract common functionality such as an HTTP login, session handling or logging into reusable components.

To get started, we can add an example middleware handler to an individual route
by adding an additional callable before the final controller like this:

```php hl_lines="3-6"
$app->get(
    '/user',
    function (Psr\Http\Message\ServerRequestInterface $request, callable $next) {
        $request = $request->withAttribute('admin', false);
        return $next($request);
    },
    function (Psr\Http\Message\ServerRequestInterface $request) {
        $role = $request->getAttribute('admin') ? 'admin' : 'user';
        return new React\Http\Message\Response(200, [], "hello $role!");
    }
);
```

For example, an HTTP `GET` request for `/user` would first call the middleware handler which then modifies this request and passes the modified request to the next controller function.

While easy to get started, it's easy to see how this would become a mess once you
keep adding more controllers to a single application.
For this reason, we recommend using middleware classes for production use-cases
like this:

```php  hl_lines="8"
# main.php

use Acme\Todo\AdminMiddleware;
use Acme\Todo\UserController;

// …

$app->get('/user', new AdminMiddleware(), new UserController());
```

```php
# src/AdminMiddleware.php
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;

class AdminMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $request = $request->withAttribute('admin', false);
        return $next($request);
    }
}
```

Likewise, you can add any number of middleware handlers to each route.
Each middleware is responsible for calling the next handler in the chain or
directly returning an error response if the request should not be processed.

Additionally, you can also add middleware to the `App` object itself to register
a global middleware handler for all registered routes:

```php hl_lines="7"
<?php

use Acme\Todo\AdminMiddleware;
use Acme\Todo\UserController;

$loop = React\EventLoop\Factory::create();
$app = new 🚀🚀🚀\App($loop, new AdminMiddleware());

$app->get('/user', new UserController());

$app->run();
$loop->run();
```

You can also combine global middleware handlers (think logging) with additional
middleware handlers for individual routes (think authentication).
Global middleware handlers will always be called before route middleware handlers.
