# Promises

> ⚠️ **Documentation still under construction**
>
> You're seeing an early draft of the documentation that is still in the works.
> Give feedback to help us prioritize.
> We also welcome [contributors](../more/community.md) to help out!

* Avoid blocking ([databases](../integrations/database.md), [filesystem](../integrations/filesystem.md), etc.)
* Deferred execution
* Concurrent execution more efficient than [multithreading](child-processes.md)
* Avoid blocking by moving blocking implementation to [child process](child-processes.md)

=== "Promise-based"

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

=== "Synchronous (for comparision)"

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

In this example, we assume an [async database](../integrations/database.md)
adapter that returns a promise which *fulfills* with some data instead of
directly returning data.

The major feature is that this means that anything that takes some time will
no longer block the entire execution.
These non-blocking operations are especially benefitial for anything that incurs
some kind of <abbrev title="Input/Output">I/O</abbrev>, such as
[database queries](../integrations/database.md), HTTP API requests,
[filesystem access](../integrations/filesystem.md) and much more.
If you want to learn more about the promise API, see also
[reactphp/promise](https://reactphp.org/promise/).

Admittedly, this example also showcases how async PHP can look slightly more
complicated than a normal, synchronous code structure.
Because we realize this API can be somewhat harder in some cases, we also
support [coroutines](coroutines.md) (and in upcoming PHP 8.1 will also support
[fibers](fibers.md)).
