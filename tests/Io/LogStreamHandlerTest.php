<?php

namespace Framework\Tests\Io;

use FrameworkX\Io\LogStreamHandler;
use PHPUnit\Framework\TestCase;

class LogStreamHandlerTest extends TestCase
{
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
}
