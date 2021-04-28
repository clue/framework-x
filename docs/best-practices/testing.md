# Testing

> ℹ️ **New to testing your web application?**
>
> While we don't want to *force* you to test your app, we want to emphasize the
> importance of automated test suites and try hard to make testing your web
> application as easy as possible.
>
> Tests allow you to verify correct behavior of your implementation, so that you
> match expected behavior with the actual implementation.
> And perhaps more importantly, by automating this process you can be sure
> future changes do not introduce any regressions and suddenly break something else.
> *Develop your application with ease and certainty.*
>
> **We ❤️ <abbrev title="Test-Driven Development">TDD</abbrev>!**

## PHPUnit basics

Once your app is structured into [dedicated controller classes](controllers.md)
as per the previous chapter, we can test each controller class in isolation.
This way, testing becomes pretty straight forward.

Let's start simple and write some unit tests for our simple `HelloController` class:

```php
# src/HelloController.php
<?php

namespace Acme\Todo;

use React\Http\Message\Response;

class HelloController
{
    public function __invoke()
    {
        return new Response(
            200,
            [],
            "Hello wörld!\n"
        );
    }
}
```

As a first step, we need to install PHPUnit with Composer by running this command
in the project directory:

```bash
$ composer require --dev phpunit/phpunit
```

> ℹ️ **New to PHPUnit?**
>
> If you haven't heard about [PHPUnit](https://phpunit.de/) before,
> PHPUnit is *the* testing framework for PHP projects.
> After installing it as a development dependency, we can take advantage of its
> structure to write tests for our own application.

Next, we can start by creating our first unit test:

```php
# tests/HelloControllerTest.php
<?php

namespace Acme\Tests\Todo;

use Acme\Todo\HelloController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;

class HelloControllerTest extends TestCase
{
    public function testControllerReturnsValidResponse()
    {
        $request = new ServerRequest('http://example.com/');

        $controller = new HelloController();
        $response = $controller($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("Hello wörld!\n", (string) $response->getBody());
    }
}
```

We're intentionally starting simple.
By starting with a controller class following a somewhat trivial implementation,
we can focus on just getting the test suite up and running first.
All following tests will also follow a somewhat similar structure, so we can
always use this as a simple building block:

* create an HTTP request object
* pass it into our controller function
* and then run assertions on the expected HTTP response object.

Once you've created your first unit tests, it's time to run PHPUnit by executing
this command in the project directory:

```
$ vendor/bin/phpunit tests
PHPUnit 9.5.4 by Sebastian Bergmann and contributors.

.                                                                   1 / 1 (100%)

Time: 00:00.006, Memory: 4.00 MB

OK (1 test, 1 assertion)
```

## Testing with specific requests

Once the basic test setup works, let's continue with testing a controller that
shows different behavior depending on what HTTP request comes in.
For this example, we're using [request attributes](../api/request.md#attributes),
but the same logic applies to testing different URLs, HTTP request headers, etc.:

```php
# src/UserController.php
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class UserController
{
    public function __invoke(ServerRequestInterface $request)
    {
        return new Response(
            200,
            [],
            "Hello " . $request->getAttribute('name') . "!\n"
        );
    }
}
```

Again, we create a new test class matching the controller class:

```php
# tests/UserControllerTest.php
<?php

namespace Acme\Tests\Todo;

use Acme\Todo\UserController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;

class UserControllerTest extends TestCase
{
    public function testControllerReturnsValidResponse()
    {
        $request = new ServerRequest('http://example.com/users/Alice');
        $request = $request->withAttribute('name', 'Alice');

        $controller = new UserController();
        $response = $controller($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("Hello Alice!\n", (string) $response->getBody());
    }
}
```

This follows the exact same logic like the previous example, except this time
we're setting up a specific HTTP request and asserting the HTTP response
contains the correct name.
Again, we can run PHPUnit in the project directory to see this works as expected:

```
$ vendor/bin/phpunit tests
PHPUnit 9.5.4 by Sebastian Bergmann and contributors.

..                                                                  2 / 2 (100%)

Time: 00:00.003, Memory: 4.00 MB

OK (2 tests, 2 assertions)
```

## Further reading

If you've made it this far, you should have a basic understanding about how
testing can help you *develop your application with ease and certainty*.
We believe mastering <abbrev title="Test-Driven Design">TTD</abbrev> is well
worth it, but perhaps this is somewhat out of scope for this documentation.
If you're curious, we recommend looking into the following topics:

* <abbrev title="Test-Driven Design">TDD</abbrev>
* Higher-level functional tests
* Test automation
* <abbrev title="Continuous Integration">CI</abbrev> / <abbrev title="Continuous Delivery/Deployment">CD</abbrev>
