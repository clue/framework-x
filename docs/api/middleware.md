# Middleware

> ℹ️ **New to middleware?**
>
> Middleware allows modifying the incoming request and outgoing response messages
and extracting this logic into reusable components.
This is frequently used for common functionality such as HTTP login, session handling, logging, and much more.

## Inline middleware functions

Middleware is any piece of logic that will wrap around your request handler.
You can add any number of middleware handlers to each route.
To get started, let's take a look at a basic middleware handler
by adding an additional callable before the final controller like this:

```php title="public/index.php"
<?php

// …

$app->get(
    '/user',
    function (Psr\Http\Message\ServerRequestInterface $request, callable $next) {
        // optionally return response without passing to next handler
        // return React\Http\Message\Response::plaintext("Done.\n");
        
        // optionally modify request before passing to next handler
        // $request = $request->withAttribute('admin', false);
        
        // call next handler in chain
        $response = $next($request);
        assert($response instanceof Psr\Http\Message\ResponseInterface);
        
        // optionally modify response before returning to previous handler
        // $response = $response->withHeader('Content-Type', 'text/plain');
        
        return $response;
    },
    function (Psr\Http\Message\ServerRequestInterface $request) {
        $role = $request->getAttribute('admin') ? 'admin' : 'user';
        return React\Http\Message\Response::plaintext("Hello $role!\n");
    }
);
```

This example shows how you could build your own middleware that can
modifying the incoming request and outgoing response messages alike.
Each middleware is responsible for calling the next handler in the chain or directly returning an error response if the request should not be processed.

## Middleware classes

While inline functions are easy to get started, it's easy to see how this would become a mess once you
keep adding more controllers to a single application.
For this reason, we recommend using middleware classes for production use-cases
like this:

```php title="src/DemoMiddleware.php"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DemoMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        // optionally return response without passing to next handler
        // return React\Http\Message\Response::plaintext("Done.\n");

        // optionally modify request before passing to next handler
        // $request = $request->withAttribute('admin', false);

        // call next handler in chain
        $response = $next($request);
        assert($response instanceof ResponseInterface);

        // optionally modify response before returning to previous handler
        // $response = $response->withHeader('Content-Type', 'text/plain');

        return $response;
    }
}
```

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\DemoMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', new DemoMiddleware(), new UserController());
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\DemoMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', DemoMiddleware::class, UserController::class);
    ```

This highlights how middleware classes provide the exact same functionaly as using inline functions,
yet provide a cleaner and more reusable structure.
Accordingly, all examples below use middleware classes as the recommended style.

> ℹ️ **New to Composer autoloading?**
>
> This example uses namespaced classes as the recommended way
> in the PHP ecosystem. If you're new to setting up your project
> structure, see also [controller classes](../best-practices/controllers.md) for more details.

## Request middleware

To get started, we can add an example middleware handler that can modify the incoming request:

```php title="src/AdminMiddleware.php"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;

class AdminMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'];
        if ($ip === '127.0.0.1') {
            $request = $request->withAttribute('admin', true);
        }

        return $next($request);
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
        $role = $request->getAttribute('admin') ? 'admin' : 'user';
        return Response::plaintext("Hello $role!\n");
    }
}
```

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\AdminMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', new AdminMiddleware(), new UserController());
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\AdminMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', AdminMiddleware::class, UserController::class);
    ```

For example, an HTTP `GET` request for `/user` would first call the middleware handler which then modifies this request and passes the modified request to the next controller function.
This is commonly used for HTTP authentication, login handling and session handling.

Note that this example only modifies the incoming request object and simply
returns whatever the next request handler returns without modifying the outgoing
response. This means this works both when the next request handler returns a
[response object](../api/response.md) synchronously or if you're using an async
request handler that may return a [promise](../async/promises.md) or
[coroutine](../async/coroutines.md). If you want to modify the outgoing response
object, see also the next chapter.

## Response middleware

Likewise, we can add an example middleware handler that can modify the outgoing response:

```php title="src/ContentTypeMiddleware.php"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ContentTypeMiddleware
{
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $response = $next($request);
        assert($response instanceof ResponseInterface);
        
        return $response->withHeader('Content-Type', 'text/plain');
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
        $name = 'Alice';
        return Response::plaintext("Hello $name!\n");
    }
}
```

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\ContentTypeMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', new ContentTypeMiddleware(), new UserController());
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\ContentTypeMiddleware;
    use Acme\Todo\UserController;

    // …

    $app->get('/user', ContentTypeMiddleware::class, UserController::class);
    ```

For example, an HTTP `GET` request for `/user` would first call the middleware handler which passes on the request to the controller function and then modifies the response that is returned by the controller function.
This is commonly used for cache handling and response body transformations (compression etc.).

Note that this example assumes the next request handler returns a
[response object](../api/response.md) synchronously. If you're writing a
middleware that also needs to support async request handlers that may
return a [promise](../async/promises.md) or [coroutine](../async/coroutines.md),
see also the next chapter.

## Async middleware

One of the core features of X is its async support.
As a consequence, each middleware handler can also return
[promises](../async/promises.md) or [coroutines](../async/coroutines.md).
While [request middleware](#request-middleware) doesn't usually have to care
about async responses, this particularly affects
[response middleware](#response-middleware) that wants to change the outgoing
response.

Here's an example middleware handler that can modify the outgoing response no
matter whether the next request handler returns a
[promise](../async/promises.md), a [coroutine](../async/coroutines.md) or
a response object synchronously:

=== "Arrow functions (PHP 7.4+)"

    ```php title="src/AsyncAwareContentTypeMiddleware.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use React\Promise\PromiseInterface;

    class AsyncContentTypeMiddleware
    {
        public function __invoke(ServerRequestInterface $request, callable $next)
        {
            $response = $next($request);

            if ($response instanceof PromiseInterface) {
                return $response->then(fn (ResponseInterface $response) => $this->handle($response));
            } elseif ($response instanceof \Generator) {
                return (fn () => $this->handle(yield from $response))();
            } else {
                return $this->handle($response);
            }
        }

        private function handle(ResponseInterface $response): ResponseInterface
        {
            return $response->withHeader('Content-Type', 'text/plain');
        }
    }
    ```

=== "Match syntax (PHP 8.0+)"

    ```php title="src/AsyncAwareContentTypeMiddleware.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use React\Promise\PromiseInterface;

    class AsyncContentTypeMiddleware
    {
        public function __invoke(ServerRequestInterface $request, callable $next)
        {
            $response = $next($request);

            return match (true) {
                $response instanceof PromiseInterface => $response->then(fn (ResponseInterface $response) => $this->handle($response)),
                $response instanceof \Generator => (fn () => $this->handle(yield from $response))(),
                default => $this->handle($response),
            };
        }

        private function handle(ResponseInterface $response): ResponseInterface
        {
            return $response->withHeader('Content-Type', 'text/plain');
        }
    }
    ```

=== "Closures"

    ```php title="src/AsyncAwareContentTypeMiddleware.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use React\Promise\PromiseInterface;

    class AsyncContentTypeMiddleware
    {
        public function __invoke(ServerRequestInterface $request, callable $next)
        {
            $response = $next($request);

            if ($response instanceof PromiseInterface) {
                return $response->then(function (ResponseInterface $response) {
                    return $this->handle($response);
                });
            } elseif ($response instanceof \Generator) {
                return (function () use ($response) {
                    return $this->handle(yield from $response);
                })();
            } else {
                return $this->handle($response);
            }
        }

        private function handle(ResponseInterface $response): ResponseInterface
        {
            return $response->withHeader('Content-Type', 'text/plain');
        }
    }
    ```

<!-- -->

=== "Coroutines"

    ```php title="src/AsyncUserController.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ServerRequestInterface;
    use React\EventLoop\Loop;
    use React\Http\Message\Response;
    use React\Promise\Promise;
    use React\Promise\PromiseInterface;

    class AsyncUserController
    {
        public function __invoke(ServerRequestInterface $request): \Generator
        {
            // async pseudo code to load some data from an external source
            $promise = $this->fetchRandomUserName();

            $name = yield $promise;
            assert(is_string($name));

            return Response::plaintext("Hello $name!\n");
        }

        /**
         * @return PromiseInterface<string>
         */
        private function fetchRandomUserName(): PromiseInterface
        {
            return new Promise(function ($resolve) {
                Loop::addTimer(0.01, function () use ($resolve) {
                    $resolve('Alice');
                });
            });
        }
    }
    ```

=== "Promises"

    ```php title="src/AsyncUserController.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ServerRequestInterface;
    use React\EventLoop\Loop;
    use React\Http\Message\Response;
    use React\Promise\Promise;
    use React\Promise\PromiseInterface;

    class AsyncUserController
    {
        /**
         * @return PromiseInterface<Response>
         */
        public function __invoke(ServerRequestInterface $request): PromiseInterface
        {
            // async pseudo code to load some data from an external source
            return $this->fetchRandomUserName()->then(function (string $name) {
                return Response::plaintext("Hello $name!\n");
            });
        }

        /**
         * @return PromiseInterface<string>
         */
        private function fetchRandomUserName(): PromiseInterface
        {
            return new Promise(function ($resolve) {
                Loop::addTimer(0.01, function () use ($resolve) {
                    $resolve('Alice');
                });
            });
        }
    }
    ```

<!-- -->

=== "Using middleware instances"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\AsyncContentTypeMiddleware;
    use Acme\Todo\AsyncUserController;

    // …

    $app->get('/user', new AsyncContentTypeMiddleware(), new AsyncUserController());
    ```

=== "Using middleware names"

    ```php title="public/index.php"
    <?php

    use Acme\Todo\AsyncContentTypeMiddleware;
    use Acme\Todo\AsyncUserController;

    // …

    $app->get('/user', AsyncContentTypeMiddleware::class, AsyncUserController::class);
    ```

For example, an HTTP `GET` request for `/user` would first call the middleware handler which passes on the request to the controller function and then modifies the response that is returned by the controller function.
This is commonly used for cache handling and response body transformations (compression etc.).

> 🔮 **Future fiber support in PHP 8.1**
>
> In the future, PHP 8.1 will provide native support for [fibers](../async/fibers.md).
> Once fibers become mainstream, we can simplify this example significantly
> because we wouldn't have to use [promises](../async/promises.md) or
> [Generator-based coroutines](../async/coroutines.md) anymore.
> See [fibers](../async/fibers.md) for more details.

## Global middleware

Additionally, you can also add middleware to the [`App`](app.md) object itself
to register a global middleware handler:

=== "Using middleware instances"

    ```php hl_lines="6" title="public/index.php"
    <?php

    use Acme\Todo\AsyncContentTypeMiddleware;
    use Acme\Todo\AsyncUserController;

    $app = new FrameworkX\App(new AdminMiddleware());

    $app->get('/user', new UserController());

    $app->run();
    ```

=== "Using middleware names"

    ```php hl_lines="6" title="public/index.php"
    <?php

    use Acme\Todo\AsyncContentTypeMiddleware;
    use Acme\Todo\AsyncUserController;

    $app = new FrameworkX\App(AdminMiddleware::class);

    $app->get('/user', UserController::class);

    $app->run();
    ```

Any global middleware handler will always be called for all registered routes
and also any requests that can not be routed.

You can also combine global middleware handlers (think logging) with additional
middleware handlers for individual routes (think authentication).
Global middleware handlers will always be called before route middleware handlers.

## Built-in middleware

### AccessLogHandler

> ⚠️ **Feature preview**
>
> This is a feature preview, i.e. it might not have made it into the current beta.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

X ships with a built-in `AccessLogHandler` middleware that is responsible for
logging any requests and responses from following middleware and controllers.
This default access log handling can be configured through the [`App`](app.md).
See [access logging](app.md#access-logging) for more details.

### ErrorHandler

> ⚠️ **Feature preview**
>
> This is a feature preview, i.e. it might not have made it into the current beta.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

X ships with a built-in `ErrorHandler` middleware that is responsible for handling
errors and exceptions returned from following middleware and controllers.
This default error handling can be configured through the [`App`](app.md).
See [error handling](app.md#error-handling) for more details.
