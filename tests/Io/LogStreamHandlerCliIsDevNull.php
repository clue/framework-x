<?php

use FrameworkX\Io\LogStreamHandler;

require __DIR__ . '/../../vendor/autoload.php';

$log = new LogStreamHandler($argv[1] ?? 'php://stdout');

$buffer = var_export($log->isDevNull(), true) . PHP_EOL;

file_put_contents($argv[2] ?? 'php://stdout', $buffer);
