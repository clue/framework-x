<?php

namespace FrameworkX\Tests;

/** Dummy attribute used to comment out code for PHP < 8 to ensure compatibility across test matrix */
#[\Attribute]
class PHP8
{
    public function __construct()
    {
        assert(\PHP_VERSION_ID >= 80000);
    }
}
