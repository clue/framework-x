<?php

namespace FrameworkX\Io;

/**
 * @internal
 */
class LogStreamHandler
{
    /** @var ?resource */
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

        // try to fstat($stream) to see if this points to /dev/null (skip on Windows)
        // @codeCoverageIgnoreStart
        $stat = false;
        if ($stream !== false && \DIRECTORY_SEPARATOR !== '\\') {
            if (\strtolower($path) === 'php://output') {
                // php://output doesn't support stat, so assume php://output will go to php://stdout
                $stdout = \defined('STDOUT') ? \STDOUT : \fopen('php://stdout', 'w');
                if (\is_resource($stdout)) {
                    $stat = \fstat($stdout);
                } else {
                    // STDOUT can not be opened => assume piping to /dev/null
                    $stream = null;
                }
            } else {
                $stat = \fstat($stream);
            }

            // close stream if it points to /dev/null
            if ($stat !== false && $stat === \stat('/dev/null')) {
                $stream = null;
            }
        }
        // @codeCoverageIgnoreEnd

        \restore_error_handler();

        if ($stream === false) {
            throw new \RuntimeException(
                'Unable to open log file "' . $path . '": ' . $errstr
            );
        }

        $this->stream = $stream;
    }

    public function isDevNull(): bool
    {
        return $this->stream === null;
    }

    public function log(string $message): void
    {
        // nothing to do if we're writing to /dev/null
        if ($this->stream === null) {
            return; // @codeCoverageIgnore
        }

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
