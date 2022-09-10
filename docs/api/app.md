# App

The `App` class is your main entrypoint to any application that builds on top of X.
It provides a simple API for routing HTTP requests as commonly used in RESTful applications.

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

// Register routes here, see routing…

$app->run();
```

## Routing

The `App` class offers a number of API methods that allow you to route incoming
HTTP requests to controller functions. In its most simple form, you can add
multiple routes using inline closures like this:

```php title="public/index.php"
<?php

// …

$app->get('/user', function () {
    return React\Http\Message\Response::plaintext("Hello everybody!\n");
});

$app->get('/user/{id}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $id = $request->getAttribute('id');
    return React\Http\Message\Response::plaintext("Hello $id!\n");
});
```

For example, an HTTP `GET` request for `/user` would call the first controller
function.
An HTTP `GET` request for `/user/alice` would call the second controller function
which also highlights how you can use [request attributes](request.md#attributes)
to access values from URI templates.

An HTTP `GET` request for `/foo` would automatically reject the HTTP request with
a `404 Not Found` error response unless this route is registered.
Likewise, an HTTP `POST` request for `/user` would reject with a `405 Method Not
Allowed` error response unless a route for this method is also registered.

You can route any number of incoming HTTP requests to controller functions by
using the matching API methods like this:

```php title="public/index.php"
<?php

// …

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

```php title="public/index.php"
<?php

// …

$app->map(['GET', 'POST'], '/user/{id}', $controller);
```

If you want to map each and every HTTP request method to a single controller,
you can use this additional shortcut:

```php title="public/index.php"
<?php

// …

$app->any('/user/{id}', $controller);
```

Any registered `GET` routes will also match HTTP `HEAD` requests by default,
unless a more explicit `HEAD` route can also be matched. Responses to HTTP `HEAD`
requests can never have a response body, so X will automatically discard any
HTTP response body in this case.

## Redirects

The `App` also offers a convenient helper method to redirect a matching route to
a new URL like this:

```php title="public/index.php"
<?php

// …

$app->redirect('/promo/reactphp', 'https://reactphp.org/');
```

Browsers and search engine crawlers will automatically follow the redirect with
the `302 Found` status code by default. You can optionally pass a custom redirect
status code in the `3xx` range to use. If this is a permanent redirect, you may
want to use the `301 Moved Permanently` status code to instruct search engine
crawlers to update their index like this:

```php title="public/index.php"
<?php

// …

$app->redirect('/blog.html', '/blog', React\Http\Message\Response::STATUS_MOVED_PERMANENTLY);
```

See [response status codes](response.md#status-codes) and [HTTP redirects](response.md#http-redirects)
for more details.

## Controllers

The above examples use inline closures as controller functions to make these
examples more concise: 

```php title="public/index.php"
<?php

// …

$app->get('/', function () {
    return React\Http\Message\Response::plaintext(
        "Hello wörld!\n"
    );
});
```

While easy to get started, it's easy to see how this would become a mess once
you keep adding more controllers to a single application.
For this reason, we recommend using [controller classes](../best-practices/controllers.md)
for production use-cases like this:

=== "Using controller instances"

    ```php title="public/index.php"
    <?php

    // …

    $app->get('/', new Acme\Todo\HelloController());
    ```

=== "Using controller names"

    ```php title="public/index.php"
    <?php

    // …

    $app->get('/', Acme\Todo\HelloController::class);
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

See [controller classes](../best-practices/controllers.md) for more details.

## Middleware

One of the main features of the `App` is middleware support.
Middleware allows you to extract common functionality such as HTTP login, session handling or logging into reusable components.
These middleware components can be added to both individual routes or globally to all registered routes.
See [middleware documentation](middleware.md) for more details.

## Error handling

Each controller function needs to return a response object in order to send
an HTTP response message. If the controller function throws an `Exception` (or
`Throwable`) or returns any invalid type, the HTTP request will automatically be
rejected with a `500 Internal Server Error` HTTP error response:

```php
<?php

// …

$app->get('/user', function () {
    throw new BadMethodCallException();
});
```

You can try out this example by sending an HTTP request like this:

```bash hl_lines="2"
$ curl -I http://localhost:8080/user
HTTP/1.1 500 Internal Server Error
…
```

Internally, the `App` will automatically add a default error handler by adding
the [`ErrorHandler`](middleware.md#errorhandler) to the list of middleware used.
You may also explicitly pass an [`ErrorHandler`](middleware.md#errorhandler)
middleware to the `App` like this:

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        new FrameworkX\ErrorHandler()
    );

    // …
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        FrameworkX\ErrorHandler::class
    );

    // …
    ```

If you do not explicitly pass an [`ErrorHandler`](middleware.md#errorhandler) or
if you pass another middleware before an [`ErrorHandler`](middleware.md#errorhandler)
to the `App`, a default error handler will be added as a first handler automatically.
You may use the [DI container configuration](../best-practices/controllers.md#container-configuration)
to configure the default error handler like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$container = new FrameworkX\Container([
    FrameworkX\ErrorHandler::class => fn () => new FrameworkX\ErrorHandler()
]);

$app = new FrameworkX\App($container);

// …
```

By default, this error message contains only few details to the client to avoid
leaking too much internal information.
If you want to implement custom error handling, you're recommended to either
catch any exceptions your own or use a custom [middleware handler](middleware.md)
to catch any exceptions in your application.

## Access log

If you're using X with its [built-in web server](../best-practices/deployment.md#built-in-web-server),
it will log all requests and responses to console output (`STDOUT`) by default.

```bash
$ php public/index.php
2023-07-21 17:30:03.617 Listening on http://0.0.0.0:8080
2023-07-21 17:30:03.725 127.0.0.1 "GET / HTTP/1.1" 200 13 0.000
2023-07-21 17:30:03.742 127.0.0.1 "GET /unknown HTTP/1.1" 404 956 0.000
```

> ℹ️ **Framework X runs anywhere**
>
> This example uses the efficient built-in web server written in pure PHP.
> We also support running behind traditional web server setups like Apache,
> nginx, and more. If you're using X behind a traditional web server, X will not
> write an access log itself, but your web server of choice can be configured to
> write an access log instead.
> See [production deployment](../best-practices/deployment.md) for more details.

Internally, the `App` will automatically add a default access log handler by
adding the [`AccessLogHandler`](middleware.md#accessloghandler) to the list of
middleware used. You may also explicitly pass an [`AccessLogHandler`](middleware.md#accessloghandler)
middleware to the `App` like this:

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        new FrameworkX\AccessLogHandler(),
        new FrameworkX\ErrorHandler()
    );

    // …
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        FrameworkX\AccessLogHandler::class,
        FrameworkX\ErrorHandler::class
    );

    // …
    ```

> ⚠️ **Feature preview**
>
> Note that the [`AccessLogHandler`](middleware.md#accessloghandler) may
> currently only be passed as a global middleware to the `App` and may not be
> used for individual routes.

If you pass an [`AccessLogHandler`](middleware.md#accessloghandler) to the `App`,
it must be followed by an [`ErrorHandler`](middleware.md#errorhandler) like in
the previous example. See also [error handling](#error-handling) for more
details.

If you do not explicitly pass an [`AccessLogHandler`](middleware.md#accessloghandler)
to the `App`, a default access log handler will be added as a first handler automatically.
You may use the [DI container configuration](../best-practices/controllers.md#container-configuration)
to configure the default access log handler like this:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$container = new FrameworkX\Container([
    FrameworkX\AccessLogHandler::class => fn () => new FrameworkX\AccessLogHandler()
]);

$app = new FrameworkX\App($container);

// …
```

X supports running behind reverse proxies just fine. However, by default it will
see the IP address of the last proxy server as the client IP address (this will
often be `127.0.0.1`). You can get the original client IP address if you configure
your proxy server to forward the original client IP address in the `X-Forwarded-For`
(XFF) or `Forwarded` HTTP request header. If you want to use these trusted headers,
you may use a custom middleware to read the IP from this header before passing
it to the [`AccessLogHandler`](middleware.md#accessloghandler) like this:

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\TrustedProxyMiddleware;

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        new TrustedProxyMiddleware(),
        new FrameworkX\AccessLogHandler(),
        new FrameworkX\ErrorHandler()
    );

    $app = new FrameworkX\App($container);

    // …
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\TrustedProxyMiddleware;

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        TrustedProxyMiddleware::class,
        FrameworkX\AccessLogHandler::class,
        FrameworkX\ErrorHandler::class
    );

    $app = new FrameworkX\App($container);

    // …
    ```

```php title="src/TrustedProxyMiddleware.php"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;

class TrustedProxyMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        // use 127.0.0.1 as trusted proxy to read from X-Forwarded-For (XFF)
        $remote_addr = $request->getAttribute('remote_addr') ?? $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if ($remote_addr === '127.0.0.1' && $request->hasHeader('X-Forwarded-For')) {
            $remote_addr = preg_replace('/,.*/', '', $request->getHeaderLine('X-Forwarded-For'));
            $request = $request->withAttribute('remote_addr', $remote_addr);
        }

        return $next($request);
    }
}
```

See also [middleware handling](middleware.md) for more details.
