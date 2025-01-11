<?php

namespace FrameworkX\Tests\Runner;

use FrameworkX\Io\LogStreamHandler;
use FrameworkX\Runner\HttpServerRunner;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use function React\Async\await;

class HttpServerRunnerTest extends TestCase
{
    public function testRunWillReportDefaultListeningAddressAndRunLoop(): void
    {
        $socket = @stream_socket_server('127.0.0.1:8080');
        if ($socket === false) {
            $this->markTestSkipped('Listen address :8080 already in use');
        }
        assert(is_resource($socket));
        fclose($socket);

        $logger = $this->createMock(LogStreamHandler::class);
        $logger->expects($this->atLeastOnce())->method('log')->withConsecutive(['Listening on http://127.0.0.1:8080']);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, null);

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);
            assert(is_resource($socket));

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $runner->run(function (): void { });
    }

    public function testRunWillReportGivenListeningAddressAndRunLoop(): void
    {
        $socket = stream_socket_server('127.0.0.1:0');
        assert(is_resource($socket));
        $addr = stream_socket_get_name($socket, false);
        assert(is_string($addr));
        fclose($socket);

        $logger = $this->createMock(LogStreamHandler::class);
        $logger->expects($this->atLeastOnce())->method('log')->withConsecutive(['Listening on http://' . $addr]);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, $addr);

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);
            assert(is_resource($socket));

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $runner->run(function (): void { });
    }

    public function testRunWillReportGivenListeningAddressWithRandomPortAndRunLoop(): void
    {
        $logger = $this->createMock(LogStreamHandler::class);
        $logger->expects($this->atLeastOnce())->method('log')->withConsecutive([$this->matches('Listening on http://127.0.0.1:%d')]);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, '127.0.0.1:0');

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () {
            $resources = get_resources();
            $socket = end($resources);
            assert(is_resource($socket));

            Loop::removeReadStream($socket);
            fclose($socket);

            Loop::stop();
        });

        $runner->run(function (): void { });
    }

    public function testRunWillRestartLoopUntilSocketIsClosed(): void
    {
        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, '127.0.0.1:0');

        // lovely: remove socket server on next tick to terminate loop
        Loop::futureTick(function () use ($logger) {
            $resources = get_resources();
            $socket = end($resources);
            assert(is_resource($socket));

            Loop::futureTick(function () use ($socket) {
                Loop::removeReadStream($socket);
                fclose($socket);

                Loop::stop();
            });

            $logger->expects($this->once())->method('log')->with('Warning: Loop restarted. Upgrade to react/async v4 recommended for production use.');
            Loop::stop();
        });

        $runner->run(function (): void { });
    }

    public function testRunWillListenForHttpRequestAndSendBackHttpResponseOverSocket(): void
    {
        $socket = stream_socket_server('127.0.0.1:0');
        assert(is_resource($socket));
        $addr = stream_socket_get_name($socket, false);
        assert(is_string($addr));
        fclose($socket);

        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, $addr);

        Loop::futureTick(function () use ($addr): void {
            $connector = new Connector();
            $promise = $connector->connect($addr);

            $promise->then(function (ConnectionInterface $connection): void {
                $connection->on('data', function (string $data): void {
                    $this->assertEquals("HTTP/1.0 200 OK\r\nContent-Length: 3\r\n\r\nOK\n", $data);
                });

                // lovely: remove socket server on client connection close to terminate loop
                $connection->on('close', function (): void {
                    $resources = get_resources();
                    end($resources);
                    prev($resources);
                    $socket = prev($resources);
                    assert(is_resource($socket));

                    Loop::removeReadStream($socket);
                    fclose($socket);

                    Loop::stop();
                });

                $connection->write("GET /unknown HTTP/1.0\r\nHost: localhost\r\n\r\n");
            });
        });

        $runner->run(function (): Response {
            return new Response(200, ['Date' => '', 'Server' => ''], "OK\n");
        });
    }

    public function testRunWillOnlyRestartLoopAfterAwaitingWhenFibersAreNotAvailable(): void
    {
        $socket = stream_socket_server('127.0.0.1:0');
        assert(is_resource($socket));
        $addr = stream_socket_get_name($socket, false);
        assert(is_string($addr));
        fclose($socket);

        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, $addr);

        Loop::futureTick(function () use ($addr, $logger): void {
            $connector = new Connector();
            $promise = $connector->connect($addr);

            // the loop will only need to be restarted if fibers are not available (PHP < 8.1)
            if (!function_exists('React\Async\async')) {
                $logger->expects($this->once())->method('log')->with('Warning: Loop restarted. Upgrade to react/async v4 recommended for production use.');
            } else {
                $logger->expects($this->never())->method('log');
            }

            $promise->then(function (ConnectionInterface $connection): void {
                $connection->on('data', function (string $data): void {
                    $this->assertEquals("HTTP/1.0 200 OK\r\nContent-Length: 3\r\n\r\nOK\n", $data);
                });

                // lovely: remove socket server on client connection close to terminate loop
                $connection->on('close', function (): void {
                    Loop::futureTick(function (): void {
                        $resources = get_resources();
                        $socket = end($resources);
                        assert(is_resource($socket));

                        Loop::removeReadStream($socket);
                        fclose($socket);

                        Loop::stop();
                    });
                });

                $connection->write("GET /unknown HTTP/1.0\r\nHost: localhost\r\n\r\n");
            });
        });

        $done = false;
        $runner->run(function () use (&$done): Response {
            $promise = new Promise(function (callable $resolve) use (&$done): void {
                Loop::futureTick(function () use ($resolve, &$done): void {
                    $resolve(null);
                    $done = true;
                });
            });
            await($promise);

            return new Response(200, ['Date' => '', 'Server' => ''], "OK\n");
        });

        // check the loop kept running after awaiting the promise
        $this->assertTrue($done);
    }

    public function testRunWillReportHttpErrorForInvalidClientRequest(): void
    {
        $socket = stream_socket_server('127.0.0.1:0');
        assert(is_resource($socket));
        $addr = stream_socket_get_name($socket, false);
        assert(is_string($addr));
        fclose($socket);

        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, $addr);

        Loop::futureTick(function () use ($addr, $logger): void {
            $connector = new Connector();
            $promise = $connector->connect($addr);

            $promise->then(function (ConnectionInterface $connection) use ($logger): void {
                $logger->expects($this->once())->method('log')->with($this->matchesRegularExpression('/^HTTP error: .*$/'));
                $connection->write("not a valid HTTP request\r\n\r\n");

                // lovely: remove socket server on client connection close to terminate loop
                $connection->on('close', function (): void {
                    $resources = get_resources();
                    end($resources);
                    prev($resources);
                    $socket = prev($resources);
                    assert(is_resource($socket));

                    Loop::removeReadStream($socket);
                    fclose($socket);

                    Loop::stop();
                });
            });
        });

        $runner->run(function (): void { });
    }

    /**
     * @requires function pcntl_signal
     * @requires function posix_kill
     */
    public function testRunWillStopWhenReceivingSigint(): void
    {
        $logger = $this->createMock(LogStreamHandler::class);
        $logger->expects($this->exactly(2))->method('log');
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, '127.0.0.1:0');

        Loop::futureTick(function () use ($logger) {
            $logger->expects($this->once())->method('log')->with('Received SIGINT, stopping loop');

            $pid = getmypid();
            assert(is_int($pid));
            posix_kill($pid, defined('SIGINT') ? SIGINT : 2);
        });

        $this->expectOutputRegex("#^\r?$#");
        $runner->run(function (): void { });
    }

    /**
     * @requires function pcntl_signal
     * @requires function posix_kill
     */
    public function testRunWillStopWhenReceivingSigterm(): void
    {
        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, '127.0.0.1:0');

        Loop::futureTick(function () use ($logger) {
            $logger->expects($this->once())->method('log')->with('Received SIGTERM, stopping loop');

            $pid = getmypid();
            assert(is_int($pid));
            posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        });

        $runner->run(function (): void { });
    }

    public function testRunWithEmptyAddressThrows(): void
    {
        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, '');

        $this->expectException(\InvalidArgumentException::class);
        $runner->run(function (): void { });
    }

    public function testRunWithBusyPortThrows(): void
    {
        $socket = stream_socket_server('127.0.0.1:0');
        assert(is_resource($socket));
        $addr = stream_socket_get_name($socket, false);
        assert(is_string($addr));

        if (@stream_socket_server($addr) !== false) {
            $this->markTestSkipped('System does not prevent listening on same address twice');
        }

        $logger = $this->createMock(LogStreamHandler::class);
        assert($logger instanceof LogStreamHandler);

        $runner = new HttpServerRunner($logger, $addr);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to listen on');
        $runner->run(function (): void { });
    }
}
