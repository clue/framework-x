# Quickstart in 5 minutes

Getting started with X is easy!
Here's a quick tutorial to get you up and running in 5 minutes or less.
Start your timer and here we go!

## Code

In order to first start using X, let's start with an entirely empty project directory.
This shouldn't be too confusing, but here's how you can do so on the command line:

```bash
$ mkdir ~/projects/acme/
$ cd ~/projects/acme/
```

Next, we can start by taking a look at a simple example application.
You can use this example to get started by creating a new `public/` directory with
an `index.php` file inside:

```php title="public/index.php"
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

On a code level, this is everything you need to get started.
For more sophisticated projects, you may want to make sure to [structure your controllers](../best-practices/controllers.md),
but the above should be just fine for starters.

## Installation

Next, we need to install X and its dependencies to actually run this project.
Thanks to [Composer](https://getcomposer.org/), this installation only requires a single command.

> ℹ️ **New to Composer?**
>
> If you haven't heard about Composer before, Composer is *the* package or dependency manager for PHP-based projects.
> It takes care of installing PHP projects and any libraries your projects depends on.
> If you haven't used it before, you have to install a recent PHP version and Composer before you can proceed.
> On Ubuntu- or Debian-based systems, this would be as simple as this:
>
> ```bash
> $ sudo apt install php-cli composer
> ```

In your project directory, simply run the following command:

```bash
$ composer require clue/framework-x:dev-main
```

X is carefully designed to be super lightweight, so this should only take a moment or two.

Once installed, your project directory should now look like this:

```
acme/
├── public/
│   └── index.php
├── vendor/
├── composer.json
└── composer.lock
```

## Running

The next step after installing all dependencies is now to serve this web application.
One of the nice properties of this project is that it *runs anywhere* (provided you have PHP installed of course).

For example, you can run the above example using the built-in web server like
this:

```bash
$ php public/index.php
```

> ℹ️ **Framework X runs anywhere**
>
> This example uses the efficient built-in web server written in pure PHP.
> We also support running behind traditional web server setups
> like Apache, nginx, Docker, and more. See [production deployment](../best-practices/deployment.md)
> for more details.

You can now use your favorite web browser or command line tool to check your web
application responds as expected:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

And that's it already, you can now stop your timer.
If you've made it this far, you should have an understanding of why X is so exciting.
As a next step, we would recommend checking out the [best practices](../../best-practices/) in order to deploy this to production.

Happy hacking!
