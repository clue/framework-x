# Fibers

Fibers allow consuming async APIs using a synchronous code flow. The `await()`
function can be used to "await" a promise or to "unwrap" its resolution value.
Fibers are a core ingredient of PHP 8.1+, but the same syntax also works on
older PHP versions to some degree if you only have limited concurrency.

## Quickstart

Let's take a look at the most basic fiber usage by using an
[async database](../integrations/database.md) integration with X:

```php title="public/index.php"
<?php

use function React\Async\await;

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore?idle=0.001';
$db = (new React\MySQL\Factory())->createLazyConnection($credentials);

$app = new FrameworkX\App();

$app->get('/book', function () use ($db) {
    $result = await($db->query(
        'SELECT COUNT(*) AS count FROM book'
    ));

    $data = "Found " . $result->resultRows[0]['count'] . " books\n";
    return React\Http\Message\Response::plaintext(
        $data
    );
});

$app->run();
```

As you can see, using an async database adapter in X is very similar to using
a normal, synchronous database adapter such as PDO. The only difference is how
the `$db->query()` call returns a promise that we call `await()` on to get the
return value.

## Requirements

X provides support for fibers out of the box, so there's nothing special you
have to install. You wouldn't usually have to directly interface with fibers,
but the underlying APIs are provided thanks to the common
[reactphp/async](https://github.com/reactphp/async) package.

Fibers are a core ingredient of PHP 8.1+ (released 2021-11-25), but the same
syntax also works on older PHP versions to some degree if you only have limited
concurrency. For production usage, we highly recommend using PHP 8.1+.

> ⚠️ **Compatibility mode**
>
> For production usage, we highly recommend using PHP 8.1+. If you're using the
> `await()` function in compatibility mode with older PHP versions, it may stop
> the loop from running and may print a warning like this:
>
> ```
> Warning: Loop restarted. Upgrade to react/async v4 recommended […]
> ```
>
> Internally, the compatibility mode will cause recursive loop executions when
> dealing with concurrent requests. This should work fine for development
> purposes and fast controllers with low concurrency, but may cause issues in
> production with high concurrency.
>
> We understand that adoption of this very new PHP version is going to take some
> time and we acknowledge that this is probably one of the largest limitations
> of using fibers at the moment for many. We're committed to providing long-term
> support (LTS) options and providing a smooth upgrade path. As such, we also
> provide limited support for older PHP versions using a compatible API without
> taking advantage of newer language features. This way, you have a much
> smoother upgrade path, as you can already start using the future API for
> testing and development purposes and upgrade your PHP version for production
> use at a later time.

## Usage

Fibers are very easy to use – because you simply can't see them – which in turn
makes them a bit harder to explain.

The gist is that whenever you're working with an async API that returns a
promise, you simply call the `await()` function on it in order to "await" its
value or to "unwrap" its resolution value. Fibers are an internal implementation
detail provided by [react/async](https://github.com/reactphp/async), so you
can simply rely on the `await()` function:

```php
<?php

use function React\Async\await;

// …

$browser = new React\Http\Browser();
$promise = $browser->get('https://example.com/');

try {
    $response = await($promise);
    assert($response instanceof Psr\Http\Message\ResponseInterface);
    echo $response->getBody();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
```

See [`await()` documentation](https://github.com/reactphp/async#await) for more
details.

> ℹ️ **Fibers vs. coroutines**
>
> In simple use cases, fibers provide the exact same functionality also offered
> by [Generator-based coroutines](coroutines.md). However, using coroutines
> internally in some API means you have to return a `Generator` or promise as a
> return value, so the calling side needs to know how to handle an async API.
> This can make integration in larger applications harder. Fibers on the other
> hand are entirely opaque to the calling side. In simple words, this means
> there's nothing special you have to take care of when using fibers anywhere
> in your APIs.

## FAQ

### When to use fibers?

As a rule of thumb, you'll likely want to use fibers when you have PHP 8.1+
available and want to use async APIs that look just like their synchronous
counterparts. This makes them much easier to integrate and there's hope this
will foster a larger ecosystem in the future.

We also provide support for [coroutines](coroutines.md) and
[promises](promises.md) on all supported PHP versions as an alternative.
Coroutines allow consuming async APIs in a way that resembles a synchronous
code flow using the `yield` keyword. You can also directly use promises as a
core building block used in all our async APIs for maximum performance.

### How do fibers work?

Fibers are a means of creating code blocks that can be paused and resumed, but
the details are a bit more involved. For more details, see our
[blog post](https://clue.engineering/2021/fibers-in-php).
