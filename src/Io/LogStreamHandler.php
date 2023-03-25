<?php

namespace FrameworkX\Io;

/**
 * @internal
 */
class LogStreamHandler
{
    /** @var resource */
    private $stream;

    /** @throws \RuntimeException if given `$path` can not be opened in append mode */
    public function __construct(string $path)
    {
        $errstr = '';
        \set_error_handler(function (int $_, string $error) use (&$errstr): bool {
            // Match errstr from PHP's warning message.
            // fopen(/dev/not-a-valid-path): Failed to open stream: Permission denied
            $errstr = \preg_replace('#.*: #', '', $error);

            return true;
        });

        $stream = \fopen($path, 'ae');
        \restore_error_handler();

        if ($stream === false) {
            throw new \RuntimeException(
                'Unable to open log file "' . $path . '": ' . $errstr
            );
        }

        $this->stream = $stream;
    }

    public function log(string $message): void
    {
        $time = \microtime(true);
        $prefix = \date('Y-m-d H:i:s', (int) $time) . \sprintf('.%03d ', (int) (($time - (int) $time) * 1e3));

        $ret = \fwrite($this->stream, $prefix . $message . \PHP_EOL);
        assert(\is_int($ret));
    }
}
