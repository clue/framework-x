--TEST--
Loading index file with NullRunner allows invoking the app
--SKIPIF--
<?php if (DIRECTORY_SEPARATOR === '\\' && @fopen(__DIR__ . '\\nul', 'a') === false) die('skip: Not supported on Windows due to https://github.com/php/php-src/security/advisories/GHSA-9f67-6fw4-hpfp'); ?>
--INI--
# suppress legacy PHPUnit 7 warning for Xdebug 3
xdebug.default_enable=
--ENV--
X_EXPERIMENTAL_RUNNER=FrameworkX\Runner\NullRunner
--FILE--
<?php

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../public/index.php';

/** @var FrameworkX\App $app */
assert($app instanceof FrameworkX\App);

$request = new React\Http\Message\ServerRequest('GET', '/');
$response = $app($request);
assert($response instanceof Psr\Http\Message\ResponseInterface);

echo $response->getBody();

?>
--EXPECT--
Hello world!
