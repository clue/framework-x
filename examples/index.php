<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

$app->get('/', function () {
    return React\Http\Message\Response::plaintext(
        "Hello world!\n"
    );
});

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    $escape = function (string $str): string {
        // replace invalid UTF-8 and control bytes with Unicode replacement character (�)
        return htmlspecialchars_decode(htmlspecialchars($str, ENT_SUBSTITUTE | ENT_DISALLOWED, 'utf-8'));
    };

    return React\Http\Message\Response::plaintext(
        "Hello " . $escape($request->getAttribute('name')) . "!\n"
    );
});

$app->get('/sleep/promise', function () {
    return React\Promise\Timer\sleep(0.1)->then(function () {
        return React\Http\Message\Response::plaintext("OK\n");
    });
});
$app->get('/sleep/coroutine', function () {
    yield React\Promise\Timer\sleep(0.1);
    return React\Http\Message\Response::plaintext("OK\n");
});
if (PHP_VERSION_ID >= 80100 && function_exists('React\Async\async')) { // requires PHP 8.1+ with react/async 4+
    $app->get('/sleep/fiber', function () {
        React\Async\await(React\Promise\Timer\sleep(0.1));
        return React\Http\Message\Response::plaintext("OK\n");
    });
}

$app->get('/uri[/{path:.*}]', function (ServerRequestInterface $request) {
    return React\Http\Message\Response::plaintext(
        (string) $request->getUri() . "\n"
    );
});

$app->get('/query', function (ServerRequestInterface $request) {
    // Returns a JSON representation of all query params passed to this endpoint.
    // Note that this assumes UTF-8 data in query params and may break for other encodings,
    // see also JSON_INVALID_UTF8_SUBSTITUTE (PHP 7.2+) or JSON_THROW_ON_ERROR (PHP 7.3+)
    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        [
            'Content-Type' => 'application/json'
        ],
        json_encode((object) $request->getQueryParams(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
});

$app->get('/debug', function (ServerRequestInterface $request) {
    ob_start();
    var_dump($request);
    $info = ob_get_clean();

    if (PHP_SAPI !== 'cli' && (!function_exists('xdebug_is_enabled') || !xdebug_is_enabled())) {
        $info = htmlspecialchars($info, 0, 'utf-8');
    }

    return React\Http\Message\Response::html(
        '<h2>Request</h2><pre>' . $info . '</pre>' . "\n"
    );
});

$app->get('/stream', function (ServerRequestInterface $request) {
    $stream = new ThroughStream();

    $loop = React\EventLoop\Loop::get();
    $timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
        $stream->write(microtime(true) . ' hi!' . PHP_EOL);
    });

    $timeout = $loop->addTimer(10.0, function () use ($timer, $loop, $stream) {
        $stream->end();
        $loop->cancelTimer($timer);
    });

    $stream->on('close', function () use ($timer, $timeout, $loop) {
        $loop->cancelTimer($timer);
        $loop->cancelTimer($timeout);
    });

    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        [
            'Content-Type' => 'text/plain;charset=utf-8'
        ],
        $stream
    );
});

$app->get('/LICENSE', new FrameworkX\FilesystemHandler(dirname(__DIR__) . '/LICENSE'));
$app->get('/source/{path:.*}', new FrameworkX\FilesystemHandler(dirname(__DIR__)));
$app->redirect('/source', '/source/');

$app->any('/method', function (ServerRequestInterface $request) {
    return React\Http\Message\Response::plaintext(
        $request->getMethod() . "\n"
    );
});

$app->get('/etag/', function (ServerRequestInterface $request) {
    $etag = '"_"';
    if ($request->getHeaderLine('If-None-Match') === $etag) {
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_NOT_MODIFIED,
            [
                'ETag' => $etag
            ]
        );
    }

    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        [
            'ETag' => $etag
        ],
        ''
    );
});
$app->get('/etag/{etag:[a-z]+}', function (ServerRequestInterface $request) {
    $etag = '"' . $request->getAttribute('etag') . '"';
    if ($request->getHeaderLine('If-None-Match') === $etag) {
        return new React\Http\Message\Response(
            React\Http\Message\Response::STATUS_NOT_MODIFIED,
            [
                'ETag' => $etag,
                'Content-Length' => strlen($etag) - 1
            ]
        );
    }

    return new React\Http\Message\Response(
        React\Http\Message\Response::STATUS_OK,
        [
            'ETag' => $etag
        ],
        $request->getAttribute('etag') . "\n"
    );
});

$app->map(['GET', 'POST'], '/headers', function (ServerRequestInterface $request) {
    // Returns a JSON representation of all request headers passed to this endpoint.
    // Note that this assumes UTF-8 data in request headers and may break for other encodings,
    // see also JSON_INVALID_UTF8_SUBSTITUTE (PHP 7.2+) or JSON_THROW_ON_ERROR (PHP 7.3+)
    return React\Http\Message\Response::json(
        (object) array_map(function (array $headers) { return implode(', ', $headers); }, $request->getHeaders())
    );
});

$app->get('/error', function () {
    throw new RuntimeException('Unable to load error');
});
$app->get('/error/null', function () {
    return null;
});
$app->get('/error/yield', function () {
    yield null;
});

// OPTIONS *
$app->options('', function () {
    return new React\Http\Message\Response(200);
});

$app->run();
