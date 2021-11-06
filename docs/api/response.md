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

* `200 OK`
* `301 Permanent Redirect`
* `302 Found` (previously `302 Temporary Redirect`)
* `304 Not Modified` (see [HTTP caching](#http-caching) below)
* `403 Forbidden`
* `404 Not Found`
* `500 Internal Server Error`
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

## HTTP caching

HTTP caching can be used to significantly improve the performance of web
applications by reusing previously fetched resources. HTTP caching is a whole
topic on its own, so this section only aims to give a basic overview of how
you can leverage HTTP caches with X. For a more in-depth overview, we highly
recommend [MDN](https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching).

HTTP supports caching for certain requests by default. In any but the most basic
use cases, it's often a good idea to explicitly specify HTTP caching headers
as part of the HTTP response to have more control over the freshness lifetime
and revalidation behavior.

### Cache-Control

The [`Cache-Control`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control)
response header can be used to control caching of responses by browsers and
shared caches such as proxies and CDNs. In its most basic form, you can use this
response header to control the lifetime of a cached response like this:

```php
<?php
// …

$app->get('/user', function () {
    $html = <<<HTML
<h1>Hello Alice</h1>
HTML;

    return new Response(
        200,
        [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'max-age=3600',
        ],
        $html
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl -I http://localhost:8080/user
HTTP/1.1 200 OK
Cache-Control: max-age=3600
…
```

### ETag

The [`ETag`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/ETag)
response header can be used for conditional requests. This ensures the response
body only needs to be transferred when it actually changes. For instance, you
can build a hash (or some other arbitrary identifier) for your contents and
check if it matches the incoming
[`If-None-Match`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-None-Match)
request header for subsequent requests. If both values match, you can send a
[`304 Not Modified`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/304)
response and omit the response body like this:

```php
<?php
// …

$app->get('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    // example body, would usually come from some kind of database
    $html = <<<HTML
<h1>Hello Alice</h1>
HTML;

    $etag = '"' . sha1($html) . '"';
    if ($request->getHeaderLine('If-None-Match') === $etag) {
        return new Response(
            304,
            [
                'Cache-Control' => 'max-age=0, must-revalidate',
                'ETag' => $etag
            ]
        );
    }

    return new Response(
        200,
        [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'ETag' => $etag
        ],
        $html
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
<h1>Hello Alice</h1>

$ curl -I http://localhost:8080/user -H 'If-None-Match: "87637b595ed5b32934c011dc6b33afb43f598865"'
HTTP/1.1 304 Not Modified
Cache-Control: max-age=0, must-revalidate
ETag: "87637b595ed5b32934c011dc6b33afb43f598865"
…
```

### Last-Modified

The [`Last-Modified`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Last-Modified)
response header can be used to signal when a response was last modified. Among
others, this can be used to ensure the response body only needs to be
transferred when it changes. For instance, you can store a timestamp or datetime
for your contents and check if it matches the incoming
[`If-Modified-Since`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/If-Modified-Since)
request header for subsequent requests. If both values match, you can send a
[`304 Not Modified`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/304)
response and omit the response body like this:

```php
<?php
// …

$app->get('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    // example date and body, would usually come from some kind of database
    $date = new DateTimeImmutable(
        '2021-11-06 13:31:04',
        new DateTimeZone('Europe/Berlin')
    );
    $html = <<<HTML
<h1>Hello Alice</h1>
HTML;

    $modified = $date->setTimezone(new DateTimeZone('UTC'))->format(DATE_RFC7231);
    if ($request->getHeaderLine('If-Modified-Since') === $modified) {
        return new Response(
            304,
            [
                'Cache-Control' => 'max-age=0, must-revalidate',
                'Last-Modified' => $modified
            ]
        );
    }

    return new Response(
        200,
        [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'max-age=0, must-revalidate',
            'Last-Modified' => $modified
        ],
        $html
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
<h1>Hello Alice</h1>

$ curl -I http://localhost:8080/user -H 'If-Modified-Since: Sat, 06 Nov 2021 12:31:04 GMT'
HTTP/1.1 304 Not Modified
Cache-Control: max-age=0, must-revalidate
Last-Modified: Sat, 06 Nov 2021 12:31:04 GMT
…
```

> ℹ️ **Working with dates**
>
> For use in HTTP, you may format any date in the GMT/UTC timezone using the
> [`DATE_RFC7231`](https://www.php.net/manual/en/class.datetimeinterface.php#datetime.constants.rfc7231)
> (PHP 7.1.5+) constant as given above. If you don't know the modification date
> for your response or don't want to expose this information, you may want to
> use [`ETag`](#etag) headers from the previous section instead.

## Output buffering

PHP provides a number of functions that write directly to the
[output buffer](https://www.php.net/manual/en/book.outcontrol.php) instead of
returning values:

* [`echo`](https://www.php.net/manual/en/function.echo.php),
  [`print`](https://www.php.net/manual/en/function.print.php),
  [`printf()`](https://www.php.net/manual/en/function.printf.php),
  [`vprintf()`](https://www.php.net/manual/en/function.vprintf.php),
  etc.
* [`var_dump()`](https://www.php.net/manual/en/function.var-dump.php),
  [`var_export()`](https://www.php.net/manual/en/function.var-export.php),
  [`print_r()`](https://www.php.net/manual/en/function.print-r.php),
  etc.
* [`readfile()`](https://www.php.net/manual/en/function.readfile.php),
  [`fpassthru()`](https://www.php.net/manual/en/function.fpassthru.php),
  [`passthru()`](https://www.php.net/manual/en/function.passthru.php),
  etc.
* …

These functions can also be used in X, but do require some special care because
we want to redirect this output to be part of an HTTP response instead. You can
start a temporary output buffer using [`ob_start()`](https://www.php.net/manual/en/function.ob-start.php)
to catch any output and return it as a response body like this:

```php
<?php
// …

$app->get('/dump', function () {
    ob_start();
    echo "Hello\n";
    var_dump(42);
    $body = ob_get_clean();

    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'text/plain; charset=utf-8'],
        $body
    );
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/dump
Hello
int(42)
```

> ℹ️ **A word of caution**
>
> Special care should be taken if the code in question is deeply nested with
> multiple return conditions or may throw an `Exception`.
>
> As a rule of thumb, output buffering should only be used as a last resort and
> directly working with `string` values is usually preferable. For instance,
> [`print_r()`](https://www.php.net/manual/en/function.print_r.php),
> [`var_export()`](https://www.php.net/manual/en/function.var-export.php) and
> others accept optional boolean flags to return the value instead of printing
> to the output buffer. In many other cases, PHP also provides alternative
> functions that directly return `string` values instead of writing to the output
> buffer. For instance, instead of using
> [`printf()`](https://www.php.net/manual/en/function.printf.php), you may want
> to use [`sprintf()`](https://www.php.net/manual/en/function.sprintf.php).

## Internal Server Error

Each controller function needs to return a response object in order to send
an HTTP response message.
If the controller functions throws an `Exception` (or `Throwable`) or any other type, the
HTTP request will automatically be rejected with a `500 Internal Server Error`
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
