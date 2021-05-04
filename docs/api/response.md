# Response

Whenever the client sends an HTTP request to our application,
we need to send back an HTTP response message.

We love standards and want to make using X as simple as possible.
That's why we build on top of the established [PSR-7 standard](https://www.php-fig.org/psr/psr-7/)
(HTTP message interfaces).
This standard defines common interfaces for HTTP request and response objects.

If you've ever used PSR-7 before, you should immediately feel at home when using X.
If you're new to PSR-7, don't worry.
Here's everything you need to know to get started.

> ℹ️ **A note about other PSR-7 implementations**
>
> All of the examples in this documentation use the
> [`React\Http\Message\Response`](https://reactphp.org/http/#response) class
> because this comes bundled as part of our dependencies.
> If you have more specific requirements or want to integrate this with an
> existing piece of code, you can use any response implementation as long as
> it implements the [`Psr\Http\Message\ResponseInterface`](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface).

## JSON

You can send JSON data as an HTTP response body like this:

```php
$app->get('/user', function () {
    $data = [
        [
            'name' => 'Alice'
        ],
        [
            'name' => 'Bob'
        ]
    ];

    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode($data)
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
[{"name":"Alice"},{"name":"Bob"}]
```

If you want to return pretty-printed JSON, all you need to do is passing the
correct flags when encoding:

```php hl_lines="14-17"
$app->get('/user', function () {
    $data = [
        [
            'name' => 'Alice'
        ],
        [
            'name' => 'Bob'
        ]
    ];

    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode(
            $data,
            JSON_PRETTY_PRINT |  JSON_UNESCAPED_SLASHES |  JSON_UNESCAPED_UNICODE
        )
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
[
    {
        "name": "Alice"
    },
    {
        "name":"Bob"
    }
]
```

This example returns a simple JSON response from some static data.
In real-world applications, you may want to load this from a
[database](../integrations/database.md).
For common API usage, you may also want to receive a [JSON request](request.md#json).

## HTML

You can send HTML data as an HTTP response body like this:

```php
$app->get('/user', function () {
    $html = <<<HTML
<h1>Hello Alice</h1>
HTML;

    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/html; charset=utf-8'],
        $html
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
<h1>Hello Alice</h1>
```

This example returns a simple HTML response from some static data.
In real-world applications, you may want to load this from a
[database](../integrations/database.md) and perhaps use
[templates](../integrations/templates.md) to render your HTML.

## Status Codes

You can assign status codes like this:

```php hl_lines="5 12"
$app->get('/user/{id}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $id = $request->getAttribute('id');
    if ($id === 'admin') {
        return new React\Http\Message\Response(
            403,
            [],
            'Forbidden'
        );
    }

    return new React\Http\Message\Response(
        200,
        [],
        "Hello $id"
    );
});
```

An HTTP request can be sent like this:

```bash hl_lines="2 6"
$ curl -I http://localhost:8080/user/Alice
HTTP/1.1 200 OK
…

$ curl -I http://localhost:8080/user/admin
HTTP/1.1 403 Forbidden
…
```

Each HTTP response message contains a status code that describes whether the
HTTP request has been successfully completed.
Here's a list of the most common HTTP status codes:

* 200 (OK)
* 301 (Permanent Redirect)
* 302 (Temporary Redirect)
* 403 (Forbidden)
* 404 (Not Found)
* 500 (Internal Server Error)
* …

See [list of HTTP status codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status) for more details.

## Headers

You can assign HTTP response headers like this:

```php hl_lines="4"
$app->get('/user', function () {
    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain; charset=utf-8'],
        "Hello wörld"
    );
});
```

An HTTP request can be sent like this:

```bash  hl_lines="3"
$ curl -I http://localhost:8080/user
HTTP/1.1 200 OK
Content-Type: text/plain; charset=utf-8
…
```

Each HTTP response message can contain an arbitrary number of response headers.
You can pass these headers as an associative array to the response object.

Additionally, the application will automatically include default headers required
by the HTTP protocol.
It's not recommended to mess with these default headers unless you're sure you
know what you're doing.

## Internal Server Error

Each controller function needs to return a response object in order to send
an HTTP response message.
If the controller functions throws an `Exception` (or `Throwable`) or any other type, the
HTTP request will automatically be rejected with a 500 (Internal Server Error)
HTTP error response:

```php
$app->get('/user', function () {
    throw new BadMethodCallException();
});
```

An HTTP request can be sent like this:

```bash hl_lines="2"
$ curl -I http://localhost:8080/user
HTTP/1.1 500 Internal Server Error
…
```

This error message contains only few details to the client to avoid leaking
internal information.
If you want to implement custom error handling, you're recommended to either
catch any exceptions your own or use a [middleware handler](middleware.md) to
catch any exceptions in your application.
