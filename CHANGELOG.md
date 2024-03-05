# Changelog

## 0.16.0 (2024-03-05)

We are thrilled to announce the official release of `v0.16.0` to the public! 🎉🚀
Additionally, we are making all previous tagged versions available to simplify the upgrade process.
In addition to the release of `v0.16.0`, this update includes all prior tagged releases.

This release includes exciting new features such as improved performance, additional options
for access logging, updates to our documentation and nginx + Apache configurations,
as well as many more internal improvements to our test suite and integration tests. 

*   Feature: Improve performance by skipping `AccessLogHandler` if it writes to `/dev/null`.
    (#248 by @clue)

*   Feature: Add optional `$path` argument for `AccessLogHandler`.
    (#247 by @clue)

*   Minor documentation improvements and update nginx + Apache configuration.
    (#245 and #251 by @clue)

*   Improve test suite with improved directory structure for integration tests.
    (#250 by @clue)

## 0.15.0 (2023-12-07)

*   Feature: Full PHP 8.3 compatibility.
    (#244 by @clue)

*   Feature: Add `App::__invoke()` method to enable custom integrations.
    (#236 by @clue)

*   Feature: Improve performance by only using `FiberHandler` for `ReactiveHandler`.
    (#237 by @clue)

*   Minor documentation improvements.
    (#242 by @yadaiio)

## 0.14.0 (2023-07-31)

*   Feature: Improve Promise v3 support and use Promise v3 template types.
    (#233 and #235 by @clue)

*   Feature: Improve handling `OPTIONS *` requests.
    (#226 by @clue)

*   Refactor logging into new `LogStreamHandler` and reactive server logic into new `ReactiveHandler`.
    (#222 and #224 by @clue)

*   Improve test suite and ensure 100% code coverage.
    (#217, #221, #225 and #228 by @clue)

## 0.13.0 (2023-02-22)

*   Feature: Forward compatibility with upcoming Promise v3.
    (#188 by @clue)

*   Feature: Full PHP 8.2 compatibility.
    (#194 and #207 by @clue)

*   Feature: Load environment variables from `$_ENV`, `$_SERVER` and `getenv()`.
    (#205 by @clue)

*   Feature: Update to support `Content-Length` response header on `HEAD` requests.
    (#186 by @clue)

*   Feature / Fix: Consistent handling for HTTP responses with multiple header values (PHP SAPI).
    (#214 by @pfk84)

*   Fix: Respect explicit response status code when Location response header is given (PHP SAPI).
    (#191 by @jkrzefski)

*   Minor documentation improvements.
    (#189 by @clue)

*   Add PHPStan to test environment on level `max` and improve type definitions.
    (#200, #201 and #204 by @clue)

*   Improve test suite and report failed assertions.
    (#199 by @clue and #208 by @SimonFrings)

## 0.12.0 (2022-08-03)

*   Feature: Support loading environment variables from DI container configuration.
    (#184 by @clue)

*   Feature: Support typed container variables for container factory functions.
    (#178, #179 and #180 by @clue)

*   Feature: Support nullable and `null` arguments and default values for DI container configuration.
    (#181 and #183 by @clue)

*   Feature: Support untyped and `mixed` arguments for container factory.
    (#182 by @clue)

## 0.11.0 (2022-07-26)

*   Feature: Make `AccessLogHandler` and `ErrorHandler` part of public API.
    (#173 and #174 by @clue)

    ```php
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(
        new FrameworkX\AccessLogHandler(),
        new FrameworkX\ErrorHandler()
    );

    // Register routes here, see routing…

    $app->run();
    ```

*   Feature: Support loading `AccessLogHandler` and `ErrorHandler` from `Container`.
    (#175 by @clue)

*   Feature: Read `$remote_addr` attribute for `AccessLogHandler` (trusted proxies).
    (#177 by @clue)

*   Internal refactoring to move all handlers to `Io` namespace.
    (#176 by @clue)

*   Update test suite to remove deprecated `utf8_decode()` (PHP 8.2 preparation).
    (#171 by SimonFrings)

## 0.10.0 (2022-07-14)

*   Feature: Built-in support for fibers on PHP 8.1+ with stable reactphp/async.
    (#168 by @clue)

    ```php
    $app->get('/book/{isbn}', function (Psr\Http\Message\ServerRequestInterface $request) use ($db) {
        $isbn = $request->getAttribute('isbn');
        $result = await($db->query(
           'SELECT title FROM book WHERE isbn = ?',
           [$isbn]
        ));

        assert($result instanceof React\MySQL\QueryResult);
        $data = $result->resultRows[0]['title'];

        return React\Http\Message\Response::plaintext(
            $data
        );
    });
    ```

*   Feature: Support PSR-11 container interface by using DI container as adapter.
    (#163 by @clue)

*   Minor documentation improvements.
    (#158 by @clue and #160 by @SimonFrings)

## 0.9.0 (2022-05-13)

*   Feature: Add signal handling support for `SIGINT` and `SIGTERM`.
    (#150 by @clue)

*   Feature: Improve error output for exception messages with special characters.
    (#131 by @clue)

*   Add new documentation chapters for Docker containers and HTTP redirecting.
    (#138 by SimonFrings and #136, #151 and #156 by @clue)

*   Minor documentation improvements.
    (#143 by @zf2timo, #153 by @mattschlosser and #129 and #154 by @clue)

*   Improve test suite and add tests for `Dockerfile` instructions.
    (#148 and #149 by @clue)

## 0.8.0 (2022-03-07)

*   Feature: Automatically start new fiber for each request on PHP 8.1+.
    (#117 by @clue)

*   Feature: Add fiber compatibility mode for PHP < 8.1.
    (#128 by @clue)

*   Improve documentation and update installation instructions for react/async.
    (#116 and #126 by @clue and #124, #125 and #127 by @SimonFrings)

*   Improve fiber tests to avoid now unneeded `await()` calls.
    (#118 by @clue)

## 0.7.0 (2022-02-05)

*   Feature: Update to use HTTP status code constants and JSON/HTML response helpers.
    (#114 by @clue)

    ```php
    $app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
        return React\Http\Message\Response::plaintext(
            "Hello " . $request->getAttribute('name') . "!\n"
        );
    });
    ```

*   Feature / Fix: Update to improve protocol handling for HTTP responses with no body.
    (#113 by @clue)

*   Minor documentation improvements.
    (#112 by @SimonFrings and #115 by @netcarver)

## 0.6.0 (2021-12-20)

*   Feature: Support automatic dependency injection by using class names (DI container).
    (#89, #92 and #94 by @clue)

    ```php
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $app = new FrameworkX\App(Acme\Todo\JsonMiddleware::class);

    $app->get('/', Acme\Todo\HelloController::class);
    $app->get('/users/{name}', Acme\Todo\UserController::class);

    $app->run();
    ```

*   Feature: Add support for explicit DI container configuration.
    (#95, #96 and #97 by @clue)

    ```php
    <?php

    require __DIR__ . '/../vendor/autoload.php';

    $container = new FrameworkX\Container([
        Acme\Todo\HelloController::class => fn() => new Acme\Todo\HelloController();
        Acme\Todo\UserController::class => function (React\Http\Browser $browser) {
            // example UserController class requires two arguments:
            // - first argument will be autowired based on class reference
            // - second argument expects some manual value
            return new Acme\Todo\UserController($browser, 42);
        }
    ]);

    // …
    ```

*   Feature: Refactor to use `$_SERVER` instead of `getenv()`.
    (#91 by @bpolaszek)

*   Minor documentation improvements.
    (#100 by @clue)

*   Update test suite to use stable PHP 8.1 Docker image.
    (#90 by @clue)

## 0.5.0 (2021-11-30)

*   Feature / BC break: Simplify `App` by always using default loop, drop optional loop instance.
    (#88 by @clue)

    ```php
    // old
    $loop = React\EventLoop\Loop::get();
    $app = new FrameworkX\App($loop); 

    // new (already supported before)
    $app = new FrameworkX\App();
    ```

*   Add documentation for manual restart of systemd service and chapter for Caddy deployment.
    (#87 by @SimonFrings and #82 by @francislavoie)

*   Improve documentation, remove leftover `$loop` references and fix typos.
    (#72 by @shuvroroy, #80 by @Ivanshamir, #81 by @clue and #83 by @rattuscz)

## 0.4.0 (2021-11-23)

We are excited to announce the official release of Framework X to the public! 🎉🚀
This release includes exciting new features such as full compatibility with PHP 8.1,
improvements to response handling, and enhanced documentation covering nginx,
Apache, and async database usage.

*   Feature: Announce Framework X public beta.
    (#64 by @clue)

*   Feature: Full PHP 8.1 compatibility.
    (#58 by @clue)

*   Feature: Improve `AccessLogHandler` and fix response size for streaming response body.
    (#47, #48, #49 and #50 by @clue)

*   Feature / Fix: Skip sending body and `Content-Length` for responses with no body.
    (#51 by @clue)

*   Feature / Fix: Consistently reject proxy requests and handle `OPTIONS *` requests.
    (#46 by @clue)

*   Add new documentation chapters for nginx, Apache and async database.
    (#57, #59 and #60 by @clue)

*   Improve documentation, examples and describe HTTP caching and output buffering.
    (#52, #53, #55, #56, #61, #62 and #63 by @clue)

## 0.3.0 (2021-09-23)

*   Feature: Add support for global middleware.
    (#23 by @clue)

*   Feature: Improve error output and refactor internal error handler.
    (#37, #39 and #41 by @clue)

*   Feature: Support changing listening address via new `X_LISTEN` environment variable.
    (#38 by @clue)

*   Feature: Update to new ReactPHP HTTP and Socket API.
    (#26 and #29 by @HLeithner and #34 by @clue)

*   Feature: Refactor to use new `AccessLogHandler`, `RouteHandler`, `RedirectHandler` and `SapiHandler`.
    (#42, #43, #44 and #45 by @clue)

*   Fix: Fix path filter regex.
    (#27 by @HLeithner)

*   Add documentation for async middleware and systemd service unit configuration.
    (#24 by @Degra1991 and #32, #35, #36 and #40 by @clue)

*   Improve test suite and run tests on Windows with PHPUnit.
    (#31 by @SimonFrings and #28 and #33 by @clue)

## 0.2.0 (2021-06-18)

*   Feature: Simplify `App` usage by making `LoopInterface` argument optional.
    (#22 by @clue)

    ```php
    // old (still supported)
    $loop = React\EventLoop\Factory::create();
    $app = new FrameworkX\App($loop);

    // new (using default loop)
    $app = new FrameworkX\App();
    ```

*   Feature: Add middleware support.
    (#18 by @clue)

*   Feature: Refactor and simplify route dispatcher.
    (#21 by @clue)

*   Feature: Add Generator-based coroutine implementation.
    (#17 by @clue)

*   Minor documentation improvements.
    (#15, #16 and #19 by @clue)

## 0.1.0 (2021-04-30)

We're excited to announce the release of the first version of Framework X in
private beta! This version marks the starting point of our project and is the
first of many milestones for making async PHP easier than ever before.

*   Release Framework X, major documentation overhaul and improve examples.
    (#14, #13 and #2 by @clue)

*   Feature: Support running behind nginx and Apache (PHP-FPM and mod_php).
    (#3, #11 and #12 by @clue)

*   Feature / Fix: Consistently parse request URI and improve URL handling.
    (#4, #5, #6 and #7 by @clue)

*   Feature: Rewrite `FilesystemHandler`, improve file access and directory listing.
    (#8 and #9 by @clue)

*   Feature: Add `any()` router method to match any request method.
    (#10 by @clue)
