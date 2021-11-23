# Database

One of the most commonly used functionality in web APIs is database access.
X supports efficient and fast database access by using async database APIs.

## Quickstart

Let's take a look at the most basic async database integration possible with X:

=== "Fibers"

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
        return new React\Http\Message\Response(
            200,
            [],
            $data
        );
    });

    $app->run();
    ```

=== "Coroutines"

    ```php title="public/index.php"
    <?php



    require __DIR__ . '/../vendor/autoload.php';

    $credentials = 'alice:secret@localhost/bookstore?idle=0.001';
    $db = (new React\MySQL\Factory())->createLazyConnection($credentials);

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

=== "Promises"

    ```php title="public/index.php"
    <?php



    require __DIR__ . '/../vendor/autoload.php';

    $credentials = 'alice:secret@localhost/bookstore?idle=0.001';
    $db = (new React\MySQL\Factory())->createLazyConnection($credentials);

    $app = new FrameworkX\App();

    $app->get('/book', function () use ($db) {
        return $db->query(
            'SELECT COUNT(*) AS count FROM book'
        )->then(function (React\MySQL\QueryResult $result) {
            $data = "Found " . $result->resultRows[0]['count'] . " books\n";
            return new React\Http\Message\Response(
                200,
                [],
                $data
            );
        });
    });

    $app->run():
    ```

As you can see, using an async database adapter in X is very similar to using
a normal, synchronous database adapter such as PDO.

### Why async?

**Because performance.**
Using async, non-blocking APIs allows much faster response times by doing
multiple things at once, instead of having to do one thing after another. This
can be seen when we process multiple concurrent operations at once (such as
sending queries to multiple databases or concurrent HTTP requests) or when
using the built-in web server which can process thousands of requests at the
same time.

Especially with Fibers, using async database APIs should be no more complicated
than their slower, synchronous counterparts. So the real question should be:
*Why not?*

### Fibers / Coroutines / Promises

The database examples showcase the three different ways to consume async APIs.
There are different reasons to pick one over the other, here's a quick overview
to help you decide.

* **Fibers** allow consuming async APIs using a synchronous code flow. The
  `await()` function can be used to "await" a promise or to "unwrap" its resolution
  value. Fibers are a core ingredient of PHP 8.1+, but the same syntax also
  works on older PHP versions to some degree if you only have limited concurrency.
  See also [Fibers](../async/fibers.md) for more details.

* **Coroutines** allow consuming async APIs in a way that resembles a synchronous
  code flow. The `yield` keyword function can be used to "await" a promise or to
  "unwrap" its resolution value. Internally, this turns the entire function into
  a `Generator` which does affect the way return values need to be accessed.
  See also [Coroutines](../async/coroutines.md) for more details.

* **Promises** are a core building block used in all our async APIs. They are
  especially useful if want to express a concurrent code flow. You can directly
  use their API for maximum performance or use Fibers or Coroutines as an easier
  way to work with async APIs.
  See also [Promises](../async/promises.md) for more details.

**Which style is better?**
We like choice.
Feel free to use whatever style works best for you.

## Database adapters

Using another database? Don't worry. ReactPHP provides support for major
database vendors through a number of ready-to-use packages:

* [MySQL](https://github.com/friends-of-reactphp/mysql)
* [Postgres](https://github.com/voryx/PgAsync)
* [SQLite](https://github.com/clue/reactphp-sqlite)
* [Redis](https://github.com/clue/reactphp-redis)
* [ClickHouse](https://github.com/clue/reactphp-clickhouse)
* And [more](https://github.com/reactphp/reactphp/wiki/Users#databases)…

Installing a new database adapter is usually as simple as executing a single
`composer require` call. Most implementations are written in pure PHP and do not
require any extensions.

All adapters provide similar APIs that allow async access to the given database.
In this documentation, we focus on MySQL because it is one of the more common
database choices for web development, but the same ideas also apply to all other
database implementations.

> ℹ️ **Legacy, blocking database access?**
>
> For performance reasons, we highly recommend using async database APIs as
> described above. For legacy integrations, we provide limited support for
> blocking database calls such as PDO, Doctrine, etc., but as a rule of thumb,
> going for an async alternative is usually somewhat more efficient.
> See [child processes](child-processes.md) for more details.

## DBAL

> ⚠️ **Feature preview**
>
> This is a feature preview, i.e. it might not have made it into the current beta.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

There is ongoing effort to provide an async DBAL (DataBase Abstraction Layer)
that will allow you to write your logic in such a way that it is not tied to a
specific database adapter.

Among others, this will make it easier to support multiple database adapters in
a single code base, which is particularly useful for reusable components such as
[middleware classes](../api/middleware.md). You may also use this to configure
different database adapters for testing purposes (such as using SQLite for
integration tests and using MySQL in production).

At the moment, we recommend using one of the above database adapters directly.
Looking forward, the idea is to add an abstraction that uses a common API and
provides a native integration with these adapters. Accordingly, switching to the
new DBAL APIs should only be a matter of a few minutes, not hours. Expect more
details later this year.

On top of this, there are ideas to build an ORM (Object-Relational Mapping) in
the future. More details will follow.

## Best practices

### Query parameters

We highly recommend using SQL statements with placeholders for query parameters
instead of manually building SQL statements by concatenating quoted strings. For
most database adapters, this would be faster, provide additional guarantees
against possible SQL injection attacks, and also make the SQL statement easier
to understand.

As a common example, we can accept a [request attribute](../api/request.md#attributes)
from a [route placeholder](../api/app.md#routing) like this:

=== "Fibers"

    ```php title="public/index.php"
    <?php

    use function React\Async\await;

    require __DIR__ . '/../vendor/autoload.php';

    $credentials = 'alice:secret@localhost/bookstore?idle=0.001';
    $db = (new React\MySQL\Factory())->createLazyConnection($credentials);

    $app = new FrameworkX\App();

    $app->get('/book/{isbn}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db) {
        $isbn = $request->getAttribute('isbn');
        $result = await($db->query(
            'SELECT title FROM book WHERE isbn = ?',
            [$isbn]
        ));
        assert($result instanceof React\MySQL\QueryResult);

        if (count($result->resultRows) === 0) {
            return new React\Http\Message\Response(
                404,
                [],
                "Book not found\n"
            );
        }

        $data = $result->resultRows[0]['title'];
        return new React\Http\Message\Response(
            200,
            [],
            $data
        );

    });

    $app->run();
    ```

=== "Coroutines"

    ```php title="public/index.php"
    <?php



    require __DIR__ . '/../vendor/autoload.php';

    $credentials = 'alice:secret@localhost/bookstore?idle=0.001';
    $db = (new React\MySQL\Factory())->createLazyConnection($credentials);

    $app = new FrameworkX\App();

    $app->get('/book/{isbn}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db) {
        $isbn = $request->getAttribute('isbn');
        $result = yield $db->query(
            'SELECT title FROM book WHERE isbn = ?',
            [$isbn]
        );
        assert($result instanceof React\MySQL\QueryResult);

        if (count($result->resultRows) === 0) {
            return new React\Http\Message\Response(
                404,
                [],
                "Book not found\n"
            );
        }

        $data = $result->resultRows[0]['title'];
        return new React\Http\Message\Response(
            200,
            [],
            $data
        );

    });

    $app->run();
    ```

=== "Promises"

    ```php title="public/index.php"
    <?php



    require __DIR__ . '/../vendor/autoload.php';

    $credentials = 'alice:secret@localhost/bookstore?idle=0.001';
    $db = (new React\MySQL\Factory())->createLazyConnection($credentials);

    $app = new FrameworkX\App();

    $app->get('/book/{isbn}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db) {
        $isbn = $request->getAttribute('isbn');
        return $db->query(
            'SELECT title FROM book WHERE isbn = ?',
            [$isbn]
        )->then(function (React\MySQL\QueryResult $result) {


            if (count($result->resultRows) === 0) {
                return new React\Http\Message\Response(
                    404,
                    [],
                    "Book not found\n"
                );
            }

            $data = $result->resultRows[0]['title'];
            return new React\Http\Message\Response(
                200,
                [],
                $data
            );
        });
    });

    $app->run();
    ```

### Recommended class structure

The above examples use inline closure definitions to ease getting started, but
it's also easy to see how this will get out of hand for more complex business
domains when you have more than a couple of routes registered.

For real-world applications, we highly recommend structuring your application
into individual [controller classes](../best-practices/controllers.md). This
way, we can break up this logic into multiple smaller files, that are easier to
work with, easier to test in isolation, and easier to reason about.

As a starting point, we recommend the following class and directory structure:

```
acme/
├── public/
│   └── index.php
├── src/
│   ├── Book.php
│   ├── BookRepository.php
│   └── BookLookupController.php
├── vendor/
├── composer.json
└── composer.lock
```

> ℹ️ **We ❤️ Domain-Driven Design**
>
> We're big fans of DDD (Domain-Driven Design), which basically is a fancy way
> of saying: The design of your application should be driven by your business
> domain requirements, not by some arbitrary technical constraints.
>
> In this instance, this means we're breaking up the database logic into their
> logic parts and using a repository pattern to isolate the entity (`Book`) from
> the database logic (`BookRepository`) and from the request logic (`BookLookupController`).
>
> For newcomers, this may sound like a lot of code at first but actually helps
> reduce clutter down the line. But don't worry, X does not enforce a particular
> style, so none of this is strictly required. Use your own best judgment,
> see [controller classes](../best-practices/controllers.md) for more details.

The main entry point [registers a route](../api/app.md#routing) for our
controller and uses dependency injection (DI) to connect all classes:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$credentials = 'alice:secret@localhost/bookstore?idle=0.001';
$db = (new React\Mysql\Factory())->createLazyConnection($credentials);
$repository = new Acme\Todo\BookRepository($db);

$app = new FrameworkX\App();

$app->get('/book/{isbn}, new Acme\Todo\BookLookupController($repository));

$app->run();
```

The main entity we're dealing with in this example is a plain PHP class which
makes it super easy to write and to use in our code:

=== "Readonly constructor property (PHP 8.1+)"

    ```php title="src/Book.php"
    <?php

    namespace Acme\Todo;

    class Book
    {






        public function __construct(public readonly string $title)
        {

        }
    }
    ```

=== "Typed property (PHP 7.4+)"

    ```php title="src/Book.php"
    <?php

    namespace Acme\Todo;

    class Book
    {



        /** @readonly **/
        public string $title;

        public function __construct(string $title)
        {
            $this->title = $title;
        }
    }
    ```

=== "Old school property"

    ```php title="src/Book.php"
    <?php

    namespace Acme\Todo;

    class Book
    {
        /**
         * @var string
         * @readonly
         */
        public $title;

        public function __construct(string $title)
        {
            $this->title = $title;
        }
    }
    ```

The database logic and request handling is separated into two classes that
interface with each other using a simple async API:

=== "Fibers"

    ```php title="src/BookRepository.php"
    <?php

    namespace Acme\Todo;

    use React\MySQL\ConnectionInterface;
    use React\MySQL\QueryResult;
    use function React\Async\await;

    class BookRepository
    {
        private $db;

        public function __construct(ConnectionInterface $db)
        {
            $this->db = $db;
        }


        public function findBook(string $isbn): ?Book
        {
            $result = await($this->db->query(
                'SELECT title FROM book WHERE isbn = ?',
                [$isbn]
            ));
            assert($result instanceof QueryResult);

            if (count($result->resultRows) === 0) {
                return null;
            }

            return new Book($result->resultRows[0]['title']);
        }
    }
    ```
    ```php title="src/BookLookupController.php"
    <?php

    namespace Acme\Todo;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use React\Http\Message\Response;


    class BookLookupController
    {
        private $repository;

        public function __construct(BookRepository $repository)
        {
            $this->repository = $repository;
        }


        public function __invoke(ServerRequestInterface $request): ResponseInterface
        {
            $isbn = $request->getAttribute('isbn');
            $book = $this->repository->findBook($isbn);


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

=== "Coroutines"

    ```php title="src/BookRepository.php"
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
    ```php title="src/BookLookupController.php"
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

=== "Promises"

    ```php title="src/BookRepository.php"
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
    ```php title="src/BookLookupController.php"
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
            });
        }
    }
    ```

That's it. We do realize that this looks like a lot of classes, but if you take
a look at the code in each class, you'll find that most of this is actually
pretty straightforward. Both the logic and the code structure itself are pretty
obvious and easy to reason about and improve upon when more features are added.

As a next step, we would highly recommend looking into
[testing](../best-practices/testing.md). Because we've broken down the logic
into very small units, it should be easy to write unit tests that allow us to
cover 100% of our logic. See [testing](../best-practices/testing.md) for more
details.

``` hl_lines="8-11"
acme/
├── public/
│   └── index.php
├── src/
│   ├── Book.php
│   ├── BookRepository.php
│   └── BookLookupController.php
├── tests/
│   ├── BookTest.php
│   ├── BookRepositoryTest.php
│   └── BookLookupControllerTest.php
├── vendor/
├── composer.json
└── composer.lock
```

The above structure is what we recommend as a starting point if you're unsure.
Once your application starts growing, you will likely want to introduce
additional layers to group logic and make cohesion between different classes
more obvious. There are multiple ways to go about this, but here are two common
structures to get you started:

=== "Group by domain"

    ``` hl_lines="5 9"
    acme/
    ├── public/
    │   └── index.php
    ├── src/
    │   ├── Book/
    │   │   ├── Book.php
    │   │   ├── BookRepository.php
    │   │   └── BookLookupController.php
    │   └── User/
    │       ├── User.php
    │       ├── UserRepository.php
    │       └── UserLookupController.php
    │
    ├── vendor/
    ├── composer.json
    └── composer.lock
    ```

=== "Group by functionality"

    ``` hl_lines="5 8 11"
    acme/
    ├── public/
    │   └── index.php
    ├── src/
    │   ├── Controllers/
    │   │   ├── BookLookupController.php
    │   │   └── UserLookupController.php
    │   ├── Entities/
    │   │   ├── Book.php
    │   │   └── User.php
    │   └── Repositories/
    │       ├── BookRepository.php
    │       └── UserRepository.php
    ├── vendor/
    ├── composer.json
    └── composer.lock
    ```

### Connection pools

> ⚠️ **Feature preview**
>
> This is a feature preview, i.e. it might not have made it into the current beta.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

If you're using X behind a [traditional web server](../best-practices/deployment.md#traditional-stacks),
there's nothing to worry about: PHP will process a single request and then clean
up afterward (shared-nothing architecture). Likewise, any database connection
will be created as part of the request handling and will be closed after the
request has been handled. Because the number of parallel PHP processes is
limited (usually through a PHP-FPM configuration), this also ensures the number
of concurrent database connections is limited.

If you're using X with its [built-in web server](../best-practices/deployment.md#built-in-web-server),
things behave differently: a single PHP process will take care of handling any
number of requests concurrently. Because this process is kept running, this
means we can reuse state such as database connections. This provides a
significant performance boost as we do not have to recreate the connection and
exchange authentication credentials for each request. As such, using the
built-in web server gives you more options on how to handle these database
connections.

* Set up a database connection for each request and clean up afterward: Same
  characteristics as traditional shared-nothing architecture. Needs to juggle
  with multiple database connection objects and missing out on significant
  performance boost.

* Create a single database connection and reuse this across multiple requests:
  Significantly less connection setup and promises noticeable performance boost.
  However, database queries will be processed in order over a single connection
  and a single slow query may thus negatively impact all following queries
  ([Head-of-line blocking](https://en.wikipedia.org/wiki/Head-of-line_blocking)).

The best compromise between both extremes is a database connection pool: Your
code interfaces with a single database connection object that will automatically
create a limited number of underlying database connections as needed.

There is ongoing effort to provide built-in support for database connection
pools for all database adapters, possible through the async DBAL described
above. Once ready, switching to the connection pool should only be a matter of
minutes, not hours. Expect more details later this year.
