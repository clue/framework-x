# Coroutines

Coroutines allow consuming async APIs in a way that resembles a synchronous code
flow. The `yield` keyword function can be used to "await" a promise or to
"unwrap" its resolution value. Internally, this turns the entire function into
a `Generator` which does affect the way return values need to be accessed.

## Quickstart

Let's take a look at the most basic coroutine usage by using an
[async database](../integrations/database.md) integration with X:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore?idle=0.001';
$db = (new React\MySQL\Factory(React\EventLoop\Loop::get()))->createLazyConnection($credentials);

$app = new FrameworkX\App();

$app->get('/book', function () use ($db) {
    $result = yield $db->query(
        'SELECT COUNT(*) AS count FROM book'
    );

    $data = "Found " . $result->resultRows[0]['count'] . " books\n";
    return new React\Http\Message\Response(
        200,
        [],
        $data
    );
});

$app->run();
```

As you can see, using an async database adapter in X is very similar to using
a normal, synchronous database adapter such as PDO. The only difference is how
the `$db->query()` call returns a promise that we use the `yield` keyword to get
the return value.

## Requirements

X provides support for Generator-based coroutines out of the box, so there's
nothing special you have to install. This works across all supported PHP
versions.

## Usage

Generator-based coroutines are very easy to use in X. The gist is that when X
calls your controller function and you're working with an async API that returns
a promise, you simply use the `yield` keyword on it in order to "await" its value
or to "unwrap" its resolution value. Internally, this turns the entire function
into a `Generator` which X can handle by consuming the generator. This is best
shown in a simple example:

```php title="public/index.php" hl_lines="11-13"
<?php

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore?idle=0.001';
$db = (new React\MySQL\Factory(React\EventLoop\Loop::get()))->createLazyConnection($credentials);

$app = new FrameworkX\App();

$app->get('/book', function () use ($db) {
    $result = yield $db->query(
        'SELECT COUNT(*) AS count FROM book'
    );

    $data = "Found " . $result->resultRows[0]['count'] . " books\n";
    return new React\Http\Message\Response(
        200,
        [],
        $data
    );
});

$app->run();
```

In simple use cases such as above, Generated-based coroutines allow consuming
async APIs in a way that resembles a synchronous code flow. However, using
coroutines internally in some API means you have to return a `Generator` or
promise as a return value, so the calling side needs to know how to handle an
async API.

This can be seen when breaking the above function up into a `BookLookupController`
and a `BookRepository`. Let's start by creating the `BookRepository` which consumes
our async database API:

```php title="src/BookRepository.php" hl_lines="18-19 21-25"
<?php

namespace Acme\Todo;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\PromiseInterface;

class BookRepository
{
    private $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /** @return \Generator<mixed,PromiseInterface,mixed,?Book> **/
    public function findBook(string $isbn): \Generator
    {
        $result = yield $this->db->query(
            'SELECT title FROM book WHERE isbn = ?',
            [$isbn]
        );
        assert($result instanceof QueryResult);

        if (count($result->resultRows) === 0) {
            return null;
        }

        return new Book($result->resultRows[0]['title']);
    }
}
```

Likewise, the `BookLookupController` consumes the API of the `BookRepository` by using
the `yield from` keyword:

```php title="src/BookLookupController.php" hl_lines="19-20 23-24"
<?php

namespace Acme\Todo;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

class BookLookupController
{
    private $repository;

    public function __construct(BookRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return \Generator<mixed,PromiseInterface,mixed,ResponseInterface> **/
    public function __invoke(ServerRequestInterface $request): \Generator
    {
        $isbn = $request->getAttribute('isbn');
        $book = yield from $this->repository->findBook($isbn);
        assert($book === null || $book instanceof Book);

        if ($book === null) {
            return new Response(
                404,
                [],
                "Book not found\n"
            );
        }

        $data = $book->title;
        return new Response(
            200,
            [],
            $data
        );
    }
}
```

As we can see, both classes need to return a `Generator` and the calling side in
turn needs to handle this. This is all taken care of by X automatically when
you use the `yield` statement anywhere in your controller function.

See also [async database APIs](../integrations/database.md#recommended-class-structure)
for more details.

## FAQ

### When to coroutines?

As a rule of thumb, you'll likely want to use fibers when you're working with
async APIs in your controllers with PHP < 8.1 and want to use these async APIs
in a way that resembles a synchronous code flow.

We also provide support for [fibers](fibers.md) which can be seen as an
additional improvement as it allows you to use async APIs that look just like
their synchronous counterparts. This makes them much easier to integrate and
there's hope this will foster an even larger async ecosystem in the future.

Additionally, also provide support for [promises](promises.md) on all supported
PHP versions as an alternative. You can directly use promises as a core building
block used in all our async APIs for maximum performance.

### How do coroutines work?

Generator-based coroutines build on top of PHP's [`Generator` class](https://www.php.net/manual/en/class.generator.php)
that will be used automatically whenever you use the `yield` keyword.

Internally, we can turn this `Generator` return value into an async promise
automatically. Whenever the `Generator` yields a value, we check it's a promise,
await its resolution, and then send the resolution value back into the `Generator`,
effectively resuming the operation on the same line.

From your perspective, this means you `yield` an async promise and the `yield`
returns a synchronous value (at a later time). Because promise resolution is
usually async, so is "awaiting" a promise from your perspective, or advancing
the `Generator` from our perspective.

See also the [`coroutine()` function](https://github.com/reactphp/async#coroutine)
for details.
