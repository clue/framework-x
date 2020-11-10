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

$app->get('/debug', function (ServerRequestInterface $request) {
    ob_start();
    var_dump($request);
    $info = ob_get_clean();

    if (PHP_SAPI !== 'cli' && !xdebug_is_enabled()) {
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

$factory = new Clue\React\SQLite\Factory($loop);
$db = $factory->openLazy('count.db', null, ['idle' => 0.001]);
$db->exec('CREATE TABLE IF NOT EXISTS hits (id INTEGER PRIMARY KEY AUTOINCREMENT, datetime STRING)');

$app->get('/count', function (ServerRequestInterface $request) use ($db) {
    $db->query('INSERT INTO hits (datetime) VALUES (?)', [date(DATE_RFC3339_EXTENDED) ]);
    return $db->query('SELECT COUNT(*) AS count FROM hits')->then(function (Clue\React\SqLite\Result $result) {
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => 'text/plain'],
            $result->rows[0]['count'] . "\n"
        );
    });
});

//$app->post('/api/streams/{topic}', new TopicAddController($db));

$app->redirect('/test', '/');

//$app->cgi('/adminer.php', __DIR__ . '/adminer.php');

$app->fs('/source/', __DIR__);
//$app->redirect('/source', '/source/');

$app->run();
$loop->run();
