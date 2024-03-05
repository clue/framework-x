# Framework X

[![CI status](https://github.com/clue-access/framework-x/workflows/CI/badge.svg)](https://github.com/clue-access/framework-x/actions)
[![code coverage](https://img.shields.io/badge/code%20coverage-100%25-success)](#tests)

Framework X – the simple and fast micro framework for building reactive web applications that run anywhere.

* [Support us](#support-us)
* [Quickstart](#quickstart)
* [Documentation](#documentation)
* [Contribute](#contribute)
* [Tests](#tests)
* [License](#license)

## Support us

We invest a lot of time developing, maintaining and updating our awesome
open-source projects. You can help us sustain this high-quality of our work by
[becoming a sponsor on GitHub](https://github.com/sponsors/clue). Sponsors get
numerous benefits in return, see our [sponsoring page](https://github.com/sponsors/clue)
for details.

Let's take these projects to the next level together! 🚀

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
$ composer require clue/framework-x:^0.16
```

> See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

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

## Contribute

You want to contribute to the Framework X source code or documentation? You've
come to the right place!

To contribute to the source code just locate the [src/](src/) folder and you'll find all
content in there. Additionally, our [tests/](tests/) folder contains all our unit
tests and acceptance tests to assure our code works as expected. For more
information on how to run the test suite check out our [testing chapter](#tests).

If you want to contribute to the [documentation](#documentation) of Framework X
found on the website, take a look inside the [docs/](docs/) folder. You'll find further
instructions inside the `README.md` in there.

Found a typo on our [website](https://framework-x.org/)? Simply go to our
[website repository](https://github.com/clue/framework-x-website)
and follow the instructions found in the `README`.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org/):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ vendor/bin/phpunit
```

The test suite is set up to always ensure 100% code coverage across all
supported environments. If you have the Xdebug extension installed, you can also
generate a code coverage report locally like this:

```bash
$ XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

Additionally, you can run our sophisticated integration tests to verify the
framework examples work as expected behind your web server. Use your web server
of choice (see deployment documentation) and execute the tests with the URL to
your installation like this:

```bash
$ php tests/integration/public/index.php
$ tests/integration.bash http://localhost:8080
```

## License

This project is released under the permissive [MIT license](LICENSE).

> Did you know that I offer custom development services and issuing invoices for
  sponsorships of releases and for contributions? Contact me (@clue) for details.
