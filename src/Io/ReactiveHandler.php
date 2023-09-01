<?php

namespace FrameworkX\Io;

use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;

/**
 * [Internal] Powerful reactive request handler built on top of ReactPHP.
 *
 * This is where the magic happens: The main `App` uses this class to run
 * ReactPHP's efficient HTTP server to handle incoming HTTP requests when
 * executed on the command line (CLI). ReactPHP's lightweight socket server can
 * listen for a large number of concurrent connections and process multiple
 * incoming connections simultaneously. The long-running server process will
 * continue to run until it is interrupted by a signal.
 *
 * Note that this is an internal class only and nothing you should usually have
 * to care about. See also the `App` and `SapiHandler` for more details.
 *
 * @internal
 */
class ReactiveHandler
{
    /** @var LogStreamHandler */
    private $logger;

    /** @var string */
    private $listenAddress;

    public function __construct(?string $listenAddress)
    {
        /** @throws void */
        $this->logger = new LogStreamHandler('php://output');
        $this->listenAddress = $listenAddress ?? '127.0.0.1:8080';
    }

    public function run(callable $handler): void
    {
        $socket = new SocketServer($this->listenAddress);

        // create HTTP server, automatically start new fiber for each request on PHP 8.1+
        $http = new HttpServer(...(\PHP_VERSION_ID >= 80100 ? [new FiberHandler(), $handler] : [$handler]));
        $http->listen($socket);

        $logger = $this->logger;
        $logger->log('Listening on ' . \str_replace('tcp:', 'http:', (string) $socket->getAddress()));

        $http->on('error', static function (\Exception $e) use ($logger): void {
            $logger->log('HTTP error: ' . $e->getMessage());
        });

        // @codeCoverageIgnoreStart
        try {
            Loop::addSignal(\defined('SIGINT') ? \SIGINT : 2, $f1 = static function () use ($socket, $logger): void {
                if (\PHP_VERSION_ID >= 70200 && \stream_isatty(\STDIN)) {
                    echo "\r";
                }
                $logger->log('Received SIGINT, stopping loop');

                $socket->close();
                Loop::stop();
            });
            Loop::addSignal(\defined('SIGTERM') ? \SIGTERM : 15, $f2 = static function () use ($socket, $logger): void {
                $logger->log('Received SIGTERM, stopping loop');

                $socket->close();
                Loop::stop();
            });
        } catch (\BadMethodCallException $e) {
            $logger->log('Notice: No signal handler support, installing ext-ev or ext-pcntl recommended for production use.');
        }
        // @codeCoverageIgnoreEnd

        do {
            Loop::run();

            if ($socket->getAddress() !== null) {
                // Fiber compatibility mode for PHP < 8.1: Restart loop as long as socket is available
                $logger->log('Warning: Loop restarted. Upgrade to react/async v4 recommended for production use.');
            } else {
                break;
            }
        } while (true);

        // remove signal handlers when loop stops (if registered)
        Loop::removeSignal(\defined('SIGINT') ? \SIGINT : 2, $f1 ?? 'printf');
        Loop::removeSignal(\defined('SIGTERM') ? \SIGTERM : 15, $f2 ?? 'printf');
    }
}
