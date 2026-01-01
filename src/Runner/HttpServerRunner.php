<?php

namespace FrameworkX\Runner;

use FrameworkX\Io\FiberHandler;
use FrameworkX\Io\LogStreamHandler;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;

/**
 * [Internal] Powerful reactive application runner built on top of ReactPHP.
 *
 * This is where the magic happens: The main `App` uses this class to run
 * ReactPHP's efficient HTTP server to handle incoming HTTP requests when
 * executed on the command line (CLI). ReactPHP's lightweight socket server can
 * listen for a large number of concurrent connections and process multiple
 * incoming connections simultaneously. The long-running server process will
 * continue to run until it is interrupted by a signal.
 *
 * Note that this is mostly an internal class and nothing you should usually
 * have to care about. For more advanced use cases, its constructor offers an
 * experimental API to customize the HTTP server, such as raising the request
 * body limit. See also the `App` and `SapiRunner` for more details.
 *
 * @internal
 */
class HttpServerRunner
{
    /** @var LogStreamHandler */
    private $logger;

    /** @var string */
    private $listenAddress;

    /** @var list<callable> */
    private $experimentalHttpMiddleware;

    /**
     * [Experimental] Create an HTTP server runner with custom configuration
     *
     * @param list<callable> $experimentalHttpMiddleware (optional) list of ReactPHP HTTP middleware to run in front of the application
     * @throws \TypeError if given $experimentalHttpMiddleware is invalid
     */
    public function __construct(LogStreamHandler $logger, ?string $listenAddress, array $experimentalHttpMiddleware = [])
    {
        if ($experimentalHttpMiddleware !== \array_values($experimentalHttpMiddleware)) {
            throw new \TypeError('Argument #3 ($experimentalHttpMiddleware) must be of type list<callable>, array given');
        }
        foreach ($experimentalHttpMiddleware as $key => $middleware) {
            /** @var mixed $middleware explicit type check for mixed if user ignores parameter type */
            if (!\is_callable($middleware)) {
                throw new \TypeError(
                    'Argument #3 ($experimentalHttpMiddleware) for key ' . $key . ' must be of type callable, ' . (\is_object($middleware) ? \get_class($middleware) : \gettype($middleware)) . ' given'
                );
            }
        }

        $this->logger = $logger;
        $this->listenAddress = $listenAddress ?? '127.0.0.1:8080';
        $this->experimentalHttpMiddleware = $experimentalHttpMiddleware;
    }

    /**
     * @param callable(\Psr\Http\Message\ServerRequestInterface):(\Psr\Http\Message\ResponseInterface|\React\Promise\PromiseInterface<\Psr\Http\Message\ResponseInterface>) $handler
     * @return void
     * @throws \InvalidArgumentException if listen address or PHP's ini settings are invalid
     * @throws \RuntimeException if listening on the given listen address fails
     */
    public function __invoke(callable $handler): void
    {
        // create HTTP server, run any experimental middleware in front of the
        // application and automatically start new fiber for each request on PHP 8.1+
        $http = new HttpServer(
            ...$this->experimentalHttpMiddleware,
            ...(\PHP_VERSION_ID >= 80100 ? [new FiberHandler(), $handler] : [$handler])
        );

        $socket = new SocketServer($this->listenAddress);
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
