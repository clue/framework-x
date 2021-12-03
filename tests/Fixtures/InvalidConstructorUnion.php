<?php

namespace FrameworkX\Tests\Fixtures;

/** PHP 8.0+ **/
class InvalidConstructorUnion
{
    public function __construct(int|float $value)
    {
    }
}
