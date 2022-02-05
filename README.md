# Framework X

[![CI status](https://github.com/clue-access/framework-x/workflows/CI/badge.svg)](https://github.com/clue-access/framework-x/actions)

Framework X – the simple and fast micro framework for building reactive web applications that run anywhere.

* [Quickstart](#quickstart)
* [Documentation](#documentation)
* [Tests](#tests)
* [License](#license)

## Quickstart

Start by creating an empty project directory.
Next, we can start by taking a look at a simple example application.
You can use this example to get started by creating a new `public/` directory with
an `index.php` file inside:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

$app->get('/', function () {
    return React\Http\Message\Response::plaintext(
        "Hello wörld!\n"
    );
});

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    return React\Http\Message\Response::plaintext(
        "Hello " . $request->getAttribute('name') . "!\n"
    );
});

$app->run();
```

Next, we need to install X and its dependencies to actually run this project.
In your project directory, simply run the following command:

```bash
$ composer require clue/framework-x:dev-main
```

That's it already! The next step is now to serve this web application.
One of the nice properties of this project is that is works both behind
traditional web server setups as well as in a stand-alone environment.

For example, you can run the above example using the built-in web server like
this:

```bash
$ php public/index.php
```

You can now use your favorite web browser or command line tool to check your web
application responds as expected:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

## Documentation

Hooked?
See [website](https://framework-x.org/) for full documentation.

Found a typo or want to contribute?
The website documentation is built from the source documentation files in
the [docs/](docs/) folder.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org/):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ vendor/bin/phpunit --stderr
```

Additionally, you can run some simple acceptance tests to verify the framework
examples work as expected behind your web server. Use your web server of choice
(see deployment documentation) and execute the tests with the URL to your
installation like this:

```bash
$ php examples/index.php
$ tests/acceptance.sh http://localhost:8080
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
