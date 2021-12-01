<?php

namespace FrameworkX\Tests\Fixtures;

/** PHP 8.1+ **/
class InvalidConstructorIntersection
{
    public function __construct(\Traversable&\ArrayAccess $value)
    {
    }
}
