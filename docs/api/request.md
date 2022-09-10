# Request

Whenever the client sends an HTTP request to our application,
we receive this as an request object and need to react to it.

We love standards and want to make using X as simple as possible.
That's why we build on top of the established [PSR-7 standard](https://www.php-fig.org/psr/psr-7/)
(HTTP message interfaces).
This standard defines common interfaces for HTTP request and response objects.

If you've ever used PSR-7 before, you should immediately feel at home when using X.
If you're new to PSR-7, don't worry.
Here's everything you need to know to get started.

> ℹ️ **A note about other PSR-7 implementations**
>
> This documentation uses the
> [`Psr\Http\Message\ServerRequestInterface`](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface)
> for all examples.
> The actual class implementing this interface is an implementation detail that
> should not be relied upon.
> If you need to construct your own instance, we recommend using the
> [`React\Http\Message\ServerRequest`](https://reactphp.org/http/#serverrequest)
> class because this comes bundled as part of our dependencies,
> but you may use any other implementation as long as
> it implements the same interface.

## Attributes

You can access request attributes like this:

```php title="public/index.php"
<?php

// …

$app->get('/user/{id}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $id = $request->getAttribute('id');

    return React\Http\Message\Response::plaintext("Hello $id!\n");
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user/Alice
Hello Alice!
```

These custom attributes are most commonly used when using URI placeholders
from [routing](app.md#routing).
Each placeholder will automatically be assigned to a matching request attribute.
See also [routing](app.md#routing) for more details.

Additionally, these custom attributes can also be useful when passing additional
information from a middleware handler to other handlers further down the chain
(think authentication information).
See also [middleware](middleware.md) for more details.

## JSON

You can access JSON data from the HTTP request body like this:

```php title="public/index.php"
<?php

// …

$app->post('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    $data = json_decode((string) $request->getBody());
    $name = $data->name ?? 'anonymous';

    return React\Http\Message\Response::plaintext("Hello $name!\n");
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user --data '{"name":"Alice"}'
Hello Alice!
```

Additionally, you may want to validate the `Content-Type: application/json` request header
to be sure the client intended to send a JSON request body.

This example returns a simple text response, you may also want to return a
[JSON response](response.md#json) for common API usage.

## Form data

You can access HTML form data from the HTTP request body like this:

```php title="public/index.php"
<?php

// …

$app->post('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    $data = $request->getParsedBody();
    $name = $data['name'] ?? 'Anonymous';

    return React\Http\Message\Response::plaintext("Hello $name!\n");
});
```


An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user -d name=Alice
Hello Alice!
```

This method returns a possibly nested array of form fields, very similar to
PHP's `$_POST` superglobal.

## Uploads

You can access any file uploads from HTML forms like this:

```php title="public/index.php"
<?php

// …

$app->post('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    $files = $request->getUploadedFiles();
    $name = isset($files['image']) ? $files['image']->getClientFilename() : 'x';

    return React\Http\Message\Response::plaintext("Uploaded $name\n");
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user -F image=@~/Downloads/image.jpg
Uploaded image.jpg
```

This method returns a possibly nested array of files uploaded, very similar
to PHP's `$_FILES` superglobal.
Each file in this array implements the `Psr\Http\Message\UploadedFileInterface`:

```php
<?php

// …

$files = $request->getUploadedFiles();
$image = $files['image'];
assert($image instanceof Psr\Http\Message\UploadedFileInterface);

$stream = $image->getStream();
assert($stream instanceof Psr\Http\Message\StreamInterface);
$contents = (string) $stream;

$size = $image->getSize();
assert(is_int($size));

$name = $image->getClientFilename();
assert(is_string($name) || $name === null);

$type = $image->getClientMediaType();
assert(is_string($type) || $name === null);
```

> ℹ️ **Info**
>
> Note that HTTP requests are currently limited to 64 KiB. Any uploads above
> this size will currently show up as an empty request body with no file uploads
> whatsoever.

## Headers

You can access all HTTP request headers like this:

```php title="public/index.php"
<?php

// …

$app->get('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    $agent = $request->getHeaderLine('User-Agent');

    return React\Http\Message\Response::plaintext("Hello $agent\n");
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user -H 'User-Agent: FrameworkX/0'
Hello FrameworkX/0
```

This example returns a simple text response with no additional response headers,
you may also want to return [response headers](response.md#headers) for common API usage.

## Parameters

You can access server-side parameters like this:

```php title="public/index.php"
<?php

// …

$app->get('/user', function (Psr\Http\Message\ServerRequestInterface $request) {
    $params = $request->getServerParams();
    $ip = $params['REMOTE_ADDR'] ?? 'unknown';

    return React\Http\Message\Response::plaintext("Hello $ip\n");
});
```

An HTTP request can be sent like this:

```bash
$ curl http://localhost:8080/user
Hello 127.0.0.1
```

This method returns an array of server-side parameters, very similar
to PHP's `$_SERVER` superglobal.
Note that available server parameters depend on the server software and version
in use.
