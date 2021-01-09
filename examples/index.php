<?php

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Stream\ThroughStream;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$app = new Frugal\App($loop);

$app->get('/', function () {
    return new React\Http\Message\Response(
        200,
        [],
        "Hello wörld!\n"
    );
});

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    return new React\Http\Message\Response(
        200,
        [],
        "Hello " . $request->getAttribute('name') . "!\n"
    );
});

$app->get('/uri', function (ServerRequestInterface $request) {
    return new React\Http\Message\Response(
        200,
        [
            'Content-Type' => 'text/plain'
        ],
        (string) $request->getUri() . "\n"
    );
});

$app->get('/query', function (ServerRequestInterface $request) {
    // Returns a JSON representation of all query params passed to this endpoint.
    // Note that this assumes UTF-8 data in query params and may break for other encodings,
    // see also JSON_INVALID_UTF8_SUBSTITUTE (PHP 7.2+) or JSON_THROW_ON_ERROR (PHP 7.3+)
    return new React\Http\Message\Response(
        200,
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

    return new React\Http\Message\Response(
        200,
        [
            'Content-Type' => 'text/html;charset=utf-8'
        ],
        '<h2>Request</h2><pre>' . $info . '</pre>' . "\n"
    );
});

$app->get('/stream', function (ServerRequestInterface $request) use ($loop) {
    $stream = new ThroughStream();

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
        200,
        [
            'Content-Type' => 'text/plain;charset=utf-8'
        ],
        $stream
    );
});

$app->redirect('/test', '/');

//$app->cgi('/adminer.php', __DIR__ . '/adminer.php');

$app->fs('/source/', __DIR__);
//$app->redirect('/source', '/source/');

$app->run();
$loop->run();
