--TEST--
Loading index file with NullRunner stops immediately without output
--INI--
# suppress legacy PHPUnit 7 warning for Xdebug 3
xdebug.default_enable=
--ENV--
X_EXPERIMENTAL_RUNNER=FrameworkX\Runner\NullRunner
--FILE_EXTERNAL--
../public/index.php
--EXPECTREGEX--
^$
