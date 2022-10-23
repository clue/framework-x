<?php

namespace FrameworkX\Tests\Fixtures;

class InvalidConstructorUntyped
{
    /** @param mixed $value */
    public function __construct($value)
    {
        assert($value === $value);
    }
}
