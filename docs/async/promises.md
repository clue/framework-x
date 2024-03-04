# Promises

Promises are a core building block used in all our async APIs. They are
especially useful if want to express a concurrent code flow. You can directly
use their API for maximum performance or use Fibers or Coroutines as an easier
way to work with async APIs.

## Quickstart

Let's take a look at the most basic promise usage by using an
[async database](../integrations/database.md) integration with X:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore';
$db = (new React\MySQL\Factory())->createLazyConnection($credentials);

$app = new FrameworkX\App();

$app->get('/book', function () use ($db) {
    return $db->query(
        'SELECT COUNT(*) AS count FROM book'
    )->then(function (React\MySQL\QueryResult $result) {
        $data = "Found " . $result->resultRows[0]['count'] . " books\n";
        return React\Http\Message\Response::plaintext(
            $data
        );
    });
});

$app->run():
```

As you can see, using an async database adapter in X with its promise-based API
is similar to using a normal, synchronous database adapter such as PDO. The
major difference is how the `$db->query()` call returns a promise that we use a
chained `then()` call on to get its fulfillment value.

## Requirements

X provides support for promises out of the box, so there's nothing special you
have to install. If you've used promises before, you'll find a common API for
promises in PHP thanks to [reactphp/promise](https://github.com/reactphp/promise).
This works across all supported PHP versions.

## Usage

If you've used promises before, you'll find that using promise-based APIs in X
is pretty straightforward. The gist is that when you're working with an async
API that returns a promise, you have to use a chained `then()` call on it in
order to "await" its fulfillment value. This is best shown in a simple example:

```php title="public/index.php" hl_lines="11-13"
<?php

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore';
$db = (new React\MySQL\Factory())->createLazyConnection($credentials);

$app = new FrameworkX\App();

$app->get('/book', function () use ($db) {
    return $db->query(
        'SELECT COUNT(*) AS count FROM book'
    )->then(function (React\MySQL\QueryResult $result) {
        $data = "Found " . $result->resultRows[0]['count'] . " books\n";
        return React\Http\Message\Response::plaintext(
            $data
        );
    });
});

$app->run():
```

Even in simple use cases such as above, promise-based APIs can take some time to
get used to. At the same time, promise-based abstractions are one of the most
efficient ways to express asynchronous APIs and as such are used throughout X
and ReactPHP's ecosystem.

One of the most obvious consequences of using promises for async APIs is that it
requires the calling side to know how to handle an async API.

This can be seen when breaking the above function up into a `BookLookupController`
and a `BookRepository`. Let's start by creating the `BookRepository` which consumes
our async database API:

```php title="src/BookRepository.php" hl_lines="18-19 21-24"
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

    /** @return PromiseInterface<?Book> **/
    public function findBook(string $isbn): PromiseInterface
    {
        return $this->db->query(
            'SELECT title FROM book WHERE isbn = ?',
            [$isbn]
        )->then(function (QueryResult $result) {
            if (count($result->resultRows) === 0) {
                return null;
            }

            return new Book($result->resultRows[0]['title']);
        });
    }
}
```

Likewise, the `BookLookupController` consumes the API of the `BookRepository` by again
using its promise-based API:

```php title="src/BookLookupController.php" hl_lines="19-20 23"
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

    /** @return PromiseInterface<ResponseInterface> **/
    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        $isbn = $request->getAttribute('isbn');
        return $this->repository->findBook($isbn)->then(function (?Book $book) {
            if ($book === null) {
                return Response::plaintext(
                    "Book not found\n"
                )->withStatus(Response::STATUS_NOT_FOUND);
            }

            $data = $book->title;
            return Response::plaintext(
                $data
            );
        });
    }
}
```

As we can see, both classes need to return a promise and the calling side in
turn needs to handle this. This is all taken care of by X automatically when
you use promises anywhere in your controller function.

See also [async database APIs](../integrations/database.md#recommended-class-structure)
for more details.

## FAQ

### When to use promises?

As a rule of thumb, promise-based APIs are one of the most efficient ways to
express asynchronous APIs and as such are used throughout X and ReactPHP's
ecosystem. You can always use promises as a core building block for async APIs
for maximum performance.

At the same time, using [fibers](fibers.md) and [coroutines](coroutines.md) is
often much easier as it allows consuming async APIs in a way that resembles a
synchronous code flow. Both build on top of promises, so there's a fair chance
you'll end up using promises one way or another no matter what.

The major feature is that this means that anything that takes some time will
no longer block the entire execution.
These non-blocking operations are especially beneficial for anything that incurs
some kind of <abbrev title="Input/Output">I/O</abbrev>, such as
[database queries](../integrations/database.md), HTTP API requests,
[filesystem access](../integrations/filesystem.md) and much more.
If you want to learn more about the promise API, see also
[reactphp/promise](https://reactphp.org/promise/).

### How do promises work?

If you want to learn more about the promise API, see also
[reactphp/promise](https://reactphp.org/promise/).
