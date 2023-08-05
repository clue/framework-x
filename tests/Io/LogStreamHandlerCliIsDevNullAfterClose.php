<?php

use FrameworkX\Io\LogStreamHandler;

require __DIR__ . '/../../vendor/autoload.php';

fclose(STDOUT);

try {
    $log = new LogStreamHandler($argv[1] ?? 'php://stdout');
} catch (\Exception $e) {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$buffer = var_export($log->isDevNull(), true) . PHP_EOL;

file_put_contents($argv[2] ?? 'php://stdout', $buffer);
