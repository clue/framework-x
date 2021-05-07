# Coroutines

> ⚠️ **Documentation still under construction**
>
> You're seeing an early draft of the documentation that is still in the works.
> Give feedback to help us prioritize.
> We also welcome [contributors](../more/community.md) to help out!

* [Promises](promises.md) can be hard due to nested callbacks
* X provides Generator-based coroutines
* Synchronous code structure, yet asynchronous execution
* Generators can be a bit harder to understand, see [Fibers](fibers.md) for future PHP 8.1 API.
    
=== "Coroutines"

    ```php
    $app->get('/book/{id:\d+}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db, $twig) {
        $row = yield $db->query(
            'SELECT * FROM books WHERE ID=?',
            [$request->getAttribute('id')]
        );

        $html = $twig->render('book.twig', $row);

        return new React\Http\Message\Response(
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            $html
        );
    });
    ```

=== "Synchronous (for comparison)"

    ```php
    $app->get('/book/{id:\d+}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db, $twig) {
        $row = $db->query(
            'SELECT * FROM books WHERE ID=?',
            [$request->getAttribute('id')]
        );

        $html = $twig->render('book.twig', $row);

        return new React\Http\Message\Response(
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            $html
        );
    });
    ```

This example highlights how async PHP can look pretty much like a normal,
synchronous code structure.
The only difference is in how the `yield` statement can be used to *await* an
async [promise](promises.md).
In order for this to work, this example assumes an
[async database](../integrations/database.md) that uses [promises](promises.md).

## Coroutines vs. Promises?

We're the first to admit that [promises](promises.md) can look more complicated,
so why offer both?

In fact, both styles exist for a reason.
Promises are used to represent an eventual return value.
Even when using coroutines, this does not change how the underlying APIs
(such as a database) still have to return promises.

If you want to *consume* a promise, you get to choose between the promise-based
API and using coroutines:

=== "Coroutines"

    ```php
    $app->get('/book/{id:\d+}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db, $twig) {
        $row = yield $db->query(
            'SELECT * FROM books WHERE ID=?',
            [$request->getAttribute('id')]
        );

        $html = $twig->render('book.twig', $row);

        return new React\Http\Message\Response(
            200,
            [
                'Content-Type' => 'text/html; charset=utf-8'
            ],
            $html
        );
    });
    ```

=== "Promises (for comparison)"

    ```php
    $app->get('/book/{id:\d+}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db, $twig) {
        return $db->query(
            'SELECT * FROM books WHERE ID=?',
            [$request->getAttribute('id')]
        )->then(function (array $row) use ($twig) {
            $html = $twig->render('book.twig', $row);

            return new React\Http\Message\Response(
                200,
                [
                    'Content-Type' => 'text/html; charset=utf-8'
                ],
                $html
            );
        });
    });
    ```

This example highlights how using coroutines in your controllers can look
somewhat easier because coroutines hide some of the complexity of async APIs.
X has a strong focus on simple APIs, so we also support coroutines.
For this reason, some people may prefer the coroutine-style async execution
model in their controllers.

At the same time, it should be pointed out that coroutines build on top of
promises.
This means that having a good understanding of how async APIs using promises
work can be somewhat beneficial.
Indeed this means that code flow could even be harder to understand for some
people, especially if you're already used to async execution models using
promise-based APIs.

**Which style is better?**
We like choice.
Feel free to use whatever style best works for you.

> 🔮 **Future fiber support in PHP 8.1**
>
> In the future, PHP 8.1 will provide native support for [fibers](fibers.md).
> Once fibers become mainstream, there would be little reason to use
> Generator-based coroutines anymore.
> While fibers will help to avoid using promises for many common use cases,
> promises will still be useful for concurrent execution.
> See [fibers](fibers.md) for more details.
