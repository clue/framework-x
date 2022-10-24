<?php

namespace FrameworkX\Tests\Fixtures;

class InvalidConstructorInt
{
    public function __construct(int $value)
    {
        assert(is_int($value));
    }
}
