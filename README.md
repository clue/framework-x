# FrugalPHP

Lightweight microframework for fast, event-driven and async-first web applications, built on top of ReactPHP.

[…]

> TODO: Introduction text, start with the why. List some noticeable features.
  Take a look at https://ninenines.eu/docs/en/cowboy/2.6/guide/modern_web/

* [Quickstart](#quickstart)
* [Basics](#basics)
    * [Installation](#installation)
    * [Structure your app (Controllers)](#structure-your-app-controllers)
    * [Testing your app](#testing-your-app)
    * [Deployment](#deployment)
* [Usage](#usage)
    * [App](#app)
    * [Request](#request)
    * [Response](#response)
    * [Database](#database)
    * [Filesystem](#filesystem)
    * [Authentication](#authentication)
    * [Sessions](#sessions)
    * [Templates](#templates)
    * [Queuing](#queuing)
* [Tests](#tests)
* [License](#license)

## Quickstart

First manually change your `composer.json` to include these lines:

```json
{
    …,
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/clue/frugalphp"
        }
    ]
}
```

> TODO: Change package name and register on Packagist.

Simply install FrugalPHP:

```bash
$ composer require clue/frugal:dev-master
```

> TODO: Tagged release.

Once everything is installed, you can now use this example to get started with
a new `app.php` file:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new Frugal\App($loop);

$app->get('/', function () {
    return new React\Http\Response(200, [], 'Hello wörld!' . "\n");
});

$loop->run();
```

That's it already! The next step is now to serve this web application.
One of the nice properties of this project is that is works both behind
traditional web server setups as well as in a stand-alone environment.

For example, you can run the above example using PHP's built-in webserver for
testing purposes like this:

```bash
$ php -S 0.0.0.0:8080 app.php
```

You can now use your favorite webbrowser or command line tool to check your web
application responds as expected:

```bash
$ curl -v http://localhost:8080/
HTTP/1.1 200 OK
…

Hello wörld!
```

## Basics

### Installation

* Runs everywhere
* Requires only PHP 7.1+, no extensions required
* Can run behind existing web servers or locally with built-in webserver (see deployment)

[…]

### Structure your app (Controllers)

Once everything is up and running, we can take a look at how to best structure
our actual web application.

To get started, it's often easiest to start with simple closure definitions
like the following:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new Frugal\App($loop);

$app->get('/', function () {
    return new React\Http\Response(200, [], 'Hello wörld!' . "\n");
});

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    return new React\Http\Response(200, [], 'Hello ' . $request->getParameter('name') . '!' . "\n");
});

$loop->run();
```

While easy to get started, this will easily get out of hand for more complex
business domains when you have more than a couple of routes registered.

For real-world applications, we highly recommend structuring your application
into invidividual controller classes. This way, we can break up the above
definition into three even simpler files:

```php
# main.php
<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new Frugal\App($loop);

$app->get('/', new Acme\HelloController());
$app->get('/users/{name}', new Acme\UserController());

$loop->run();
```
```php
# src/HelloController.php
<?php

class HelloController
{
    public function __invoke()
    {
        return new React\Http\Response(200, [], 'Hello wörld!' . "\n");
    }
}
```
```php
# src/UserController.php
<?php

class UserController
{
    public function __invoke(Psr\Http\Message\ServerRequestInterface $request)
    {
        return new React\Http\Response(200, [], 'Hello ' . $request->getParameter('name') . '!' . "\n");
    }
}
```

Doesn't look too complex, right? Now, we only need to tell Composer's autoloader
about our vendor namespace `Acme` in the `src/` folder. Make sure to include the
following lines in your `composer.json` file:

```json
{
    "autoload": {
        "psr-4": {
            "Acme\\": "src/"
        }
    }
}
```

When we're doing this the first time, we have to update Composer's generated
autoloader classes:

```bash
$ composer dump-autoload
```

Don't worry, that's a one-time setup only. If you're used to working with
Composer, this shouldn't be too surprising. If this sounds new to you, rest
assured this is the only time you have to worry about this, new classes can
simply be added without having to run Composer again.

Again, let's see our web application still works by using your favorite
webbrowser or command line tool:

```bash
$ curl -v http://localhost:8080/
HTTP/1.1 200 OK
…

Hello wörld!
```

### Testing your app

**We ❤️ TDD and DDD!**

New to testing your web application? While we don't want to *force* you to test
your app, we want to emphasize the importance of automated test suits and try hard
to make testing your web application as easy as possible.

Once your app is structured into dedicated controller classes as per the previous
chapter, […]

> TODO: PHPUnit setup basics and first test cases.

> TODO: Higher-level functional tests.

### Deployment

Runs everywhere:

* Built-in webserver
* Apache & nginx
* Standalone

[…]

## Usage

### App

* Batteries included, but swappable
* Providing HTTP routing (RESTful applications)

### Request

* PSR-7

### Response

* PSR-7

### Database

* Async
* No PDO, no Doctrine and family
* Easy to spot, harder to replace
* MySQL, Postgres and SQLite supported
* ORM
* Redis

### Filesystem

* Async
* No `fopen()`, `file_get_contents()` and family
* Easy to overlook
* Few blocking calls *can* be acceptable

### Authentication

* Basic auth easy
* HTTP middleware better
* JWT and oauth possible

### Sessions

* Built-in (or module?)
* HTTP middleware
* Persistence via database/ORM or other mechanism?

### Templates

* Any template language possible
* Twig recommended?

### Queuing

* Built-in (or module?)
* Redis built-in, but swappable with real instance (constraints?)

## Tests

You can run some simple acceptance tests to verify the frameworks works
as expected by running:

```bash
$ tests/acceptance.sh http://localhost:8080
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
