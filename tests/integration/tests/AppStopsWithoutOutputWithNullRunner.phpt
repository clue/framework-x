--TEST--
Loading index file with NullRunner stops immediately without output
--SKIPIF--
<?php if (DIRECTORY_SEPARATOR === '\\' && @fopen(__DIR__ . '\\nul', 'a') === false) die('skip: Not supported on Windows due to https://github.com/php/php-src/security/advisories/GHSA-9f67-6fw4-hpfp'); ?>
--INI--
# suppress legacy PHPUnit 7 warning for Xdebug 3
xdebug.default_enable=
--ENV--
X_EXPERIMENTAL_RUNNER=FrameworkX\Runner\NullRunner
--FILE_EXTERNAL--
../public/index.php
--EXPECTREGEX--
^$
