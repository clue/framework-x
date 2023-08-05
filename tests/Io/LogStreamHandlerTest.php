<?php

namespace Framework\Tests\Io;

use FrameworkX\Io\LogStreamHandler;
use PHPUnit\Framework\TestCase;

class LogStreamHandlerTest extends TestCase
{
    public static function provideFilesystemPaths(): \Generator
    {
        yield [
            __FILE__,
            true
        ];
        yield [
            __FILE__ . "\0",
            false
        ];
        yield [
            str_replace(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, __FILE__),
            true
        ];
        yield [
            str_replace(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR === '\\' ? '/' : '\\', __FILE__),
            DIRECTORY_SEPARATOR === '\\'
        ];

        yield [
            'access.log',
            false
        ];
        yield [
            './access.log',
            false
        ];
        yield [
            '../access.log',
            false
        ];
        yield [
            '.\\access.log',
            false
        ];
        yield [
            '..\\access.log',
            false
        ];
        yield [
            '\\\\access.log',
            false
        ];
        if (DIRECTORY_SEPARATOR === '\\') {
            // invalid paths on Windows, technically valid on Unix but unlikely to be writable here
            yield [
                '/access.log',
                false
            ];
            yield [
                '//access.log',
                false
            ];
        }

        yield [
            '',
            false
        ];
        yield [
            '.',
            false
        ];
        yield [
            '..',
            false
        ];
        yield [
            __DIR__ . DIRECTORY_SEPARATOR . "\0",
            false
        ];

        yield [
            '/dev/null',
            DIRECTORY_SEPARATOR !== '\\'
        ];
        yield [
            'nul',
            false
        ];
        yield [
            '\\\\.\\nul',
            false
        ];
        if (DIRECTORY_SEPARATOR === '\\') {
            // valid path on Windows, but we don't want to write here on Unix
            yield [
                __DIR__ . DIRECTORY_SEPARATOR . 'nul',
                true
            ];
            yield [
                __DIR__ . DIRECTORY_SEPARATOR . 'NUL',
                true
            ];
        }

        yield [
            'php://stdout',
            true
        ];
        yield [
            'PHP://STDOUT',
            true
        ];
        yield [
            'php:stdout',
            false
        ];

        yield [
            'php://stderr',
            true
        ];
        yield [
            'PHP://STDERR',
            true
        ];
        yield [
            'php:stderr',
            false
        ];
    }

    public static function provideValidPaths(): \Generator
    {
        foreach (self::provideFilesystemPaths() as [$path, $valid]) {
            if ($valid) {
                yield [$path];
            }
        }
    }

    /**
     * @dataProvider providevalidPaths
     * @doesNotPerformAssertions
     */
    public function testCtorWithValidPathWorks(string $path): void
    {
        new LogStreamHandler($path);
    }

    public static function provideInvalidPaths(): \Generator
    {
        foreach (self::provideFilesystemPaths() as [$path, $valid]) {
            if (!$valid) {
                yield [$path];
            }
        }
    }

    /**
     * @dataProvider provideInvalidPaths
     */
    public function testCtorWithInvalidPathThrows(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to open log file "' . addslashes($path) . '": Invalid path given');
        new LogStreamHandler($path);
    }

    public function testCtorWithDirectoryInsteadOfFileThrowsWithoutCallingGlobalErrorHandler(): void
    {
        $called = 0;
        set_error_handler($new = function () use (&$called): bool {
            ++$called;
            return false;
        });

        try {
            try {
                new LogStreamHandler(__DIR__);
            } finally {
                $previous = set_error_handler(function (): bool { return false; });
                restore_error_handler();
                restore_error_handler();
            }
            $this->fail();
        } catch (\RuntimeException $e) {
            $errstr = DIRECTORY_SEPARATOR === '\\' ? 'Permission denied' : 'Is a directory';
            $this->assertEquals('Unable to open log file "' . __DIR__ . '": ' . $errstr, $e->getMessage());

            $this->assertEquals(0, $called);
            $this->assertSame($new, $previous ?? null);
        }
    }

    public function testLogWithMemoryStreamWritesMessageWithCurrentDateAndTime(): void
    {
        $logger = new LogStreamHandler('php://memory');

        $logger->log('Hello');

        $ref = new \ReflectionProperty($logger, 'stream');
        $ref->setAccessible(true);
        $stream = $ref->getValue($logger);
        assert(is_resource($stream));

        rewind($stream);
        $output = stream_get_contents($stream);
        assert(is_string($output));

        // 2021-01-29 12:22:01.717 Hello\n
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output); // @phpstan-ignore-line
        } else {
            // legacy PHPUnit < 9.1
            $this->assertRegExp("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output);
        }
    }

    public function testLogWithOutputStreamPrintsMessageWithCurrentDateAndTime(): void
    {
        $logger = new LogStreamHandler('php://output');

        // 2021-01-29 12:22:01.717 Hello\n
        $this->expectOutputRegex("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/");
        $logger->log('Hello');
    }

    public function testLogWithPathToNewFileWillCreateNewFileWithLogMessageAndCurrentDateAndTime(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'log');
        assert(is_string($path));
        unlink($path);

        $logger = new LogStreamHandler($path);

        $logger->log('Hello');

        $output = file_get_contents($path);
        assert(is_string($output));

        // 2021-01-29 12:22:01.717 Hello\n
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output); // @phpstan-ignore-line
        } else {
            // legacy PHPUnit < 9.1
            $this->assertRegExp("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output);
        }

        unset($logger);
        unlink($path);
    }

    public function testLogWithPathToExistingFileWillAppendLogMessageWithCurrentDateAndTime(): void
    {
        $stream = tmpfile();
        assert(is_resource($stream));
        fwrite($stream, 'First' . PHP_EOL);

        $meta = stream_get_meta_data($stream);
        assert(is_string($meta['uri']));

        $logger = new LogStreamHandler($meta['uri']);

        $logger->log('Hello');

        rewind($stream);
        $output = stream_get_contents($stream);
        assert(is_string($output));

        // First\n
        // 2021-01-29 12:22:01.717 Hello\n
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression("/^First" . PHP_EOL . "\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output); // @phpstan-ignore-line
        } else {
            // legacy PHPUnit < 9.1
            $this->assertRegExp("/^First" . PHP_EOL . "\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3} Hello" . PHP_EOL . "$/", $output);
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testLogWithDevNullWritesNothing(): void
    {
        $logger = new LogStreamHandler(DIRECTORY_SEPARATOR !== '\\' ? '/dev/null' : __DIR__ . '\\nul');

        $logger->log('Hello');
    }

    public function testIsDevNullReturnsFalseForCurrentFile(): void
    {
        $logger = new LogStreamHandler(__FILE__);

        $this->assertFalse($logger->isDevNull());
    }

    public function testIsDevNullReturnsTrueForDevNull(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $logger = new LogStreamHandler('/dev/null');

        $this->assertTrue($logger->isDevNull());
    }

    public function testIsDevNullReturnsTrueForSymlinkToDevNull(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $path = tempnam(sys_get_temp_dir(), 'null');
        assert(is_string($path));
        unlink($path);
        symlink('/dev/null', $path);

        $logger = new LogStreamHandler($path);

        $this->assertTrue($logger->isDevNull());

        unlink($path);
    }

    public function testIsDevNullReturnsFalseForStdoutInChildProcess(): void
    {
        $pipes = [];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'LogStreamHandlerCliIsDevNull.php'),
            [
                0 => STDIN,
                1 => ['pipe', 'w'],
                2 => STDERR
            ],
            $pipes,
            null,
            null,
            [
                'bypass_shell' => true
            ]
        );
        assert(is_resource($process));

        assert(is_resource($pipes[1]));
        $output = stream_get_contents($pipes[1]);
        assert(is_string($output));
        proc_close($process);

        $this->assertEquals('false' . PHP_EOL, $output);
    }

    public function testIsDevNullReturnsTrueForStdoutInChildProcessRedirectedToDevNull(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $pipes = [];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'LogStreamHandlerCliIsDevNull.php') . ' php://stdout php://stderr',
            [
                0 => STDIN,
                1 => ['file', '/dev/null', 'r'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            null,
            null,
            [
                'bypass_shell' => true
            ]
        );
        assert(is_resource($process));

        assert(is_resource($pipes[2]));
        $output = stream_get_contents($pipes[2]);
        assert(is_string($output));
        proc_close($process);

        $this->assertEquals('true' . PHP_EOL, $output);
    }

    public function testIsDevNullReturnsTrueForOutputStreamInChildProcessRedirectedToDevNull(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $pipes = [];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/LogStreamHandlerCliIsDevNull.php') . ' php://output php://stderr',
            [
                0 => STDIN,
                1 => ['file', '/dev/null', 'r'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            null,
            null,
            [
                'bypass_shell' => true
            ]
        );
        assert(is_resource($process));

        assert(is_resource($pipes[2]));
        $output = stream_get_contents($pipes[2]);
        assert(is_string($output));
        proc_close($process);

        $this->assertEquals('true' . PHP_EOL, $output);
    }

    public function testIsDevNullReturnsTrueForOutputStreamReferencingClosedStdoutInChildProcess(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $pipes = [];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/LogStreamHandlerCliIsDevNullAfterClose.php') . ' php://output php://stderr',
            [
                0 => STDIN,
                1 => STDOUT,
                2 => ['pipe', 'w']
            ],
            $pipes,
            null,
            null,
            [
                'bypass_shell' => true
            ]
        );
        assert(is_resource($process));

        assert(is_resource($pipes[2]));
        $output = stream_get_contents($pipes[2]);
        assert(is_string($output));
        proc_close($process);

        $this->assertEquals('true' . PHP_EOL, $output);
    }

    public function testCtorWithStdoutStreamThrowsIfStdoutIsAlreadyClosed(): void
    {
        $pipes = [];
        $process = proc_open(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/LogStreamHandlerCliIsDevNullAfterClose.php') . ' php://stdout php://stderr',
            [
                0 => STDIN,
                1 => STDOUT,
                2 => ['pipe', 'w']
            ],
            $pipes,
            null,
            null,
            [
                'bypass_shell' => true
            ]
        );
        assert(is_resource($process));

        assert(is_resource($pipes[2]));
        $output = stream_get_contents($pipes[2]);
        assert(is_string($output));
        proc_close($process);

        $this->assertEquals('RuntimeException: Unable to open log file "php://stdout": operation failed' . PHP_EOL, $output);
    }
}
