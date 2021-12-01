<?php

namespace FrameworkX\Tests\Fixtures;

class InvalidConstructorUnknown
{
    public function __construct(\UnknownClass $value)
    {
    }
}
