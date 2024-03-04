<?php

namespace FrameworkX\Io;

/**
 * @internal
 */
class LogStreamHandler
{
    /** @var resource */
    private $stream;

    /**
     * @param string $path absolute log file path
     * @throws \InvalidArgumentException if given `$path` is not an absolute file path
     * @throws \RuntimeException if given `$path` can not be opened in append mode
     */
    public function __construct(string $path)
    {
        if (\strpos($path, "\0") !== false || (\stripos($path, 'php://') !== 0 && !$this->isAbsolutePath($path))) {
            throw new \InvalidArgumentException(
                'Unable to open log file "' . \addslashes($path) . '": Invalid path given'
            );
        }

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

    private function isAbsolutePath(string $path): bool
    {
        return \DIRECTORY_SEPARATOR !== '\\' ? \substr($path, 0, 1) === '/' : (bool) \preg_match('#^[A-Z]:[/\\\\]#i', $path);
    }
}
