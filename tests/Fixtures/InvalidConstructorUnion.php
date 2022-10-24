<?php

namespace FrameworkX\Tests\Fixtures;

/** PHP 8.0+ **/
class InvalidConstructorUnion
{
    // @phpstan-ignore-next-line for PHP < 8
    #[PHP8] public function __construct(int|float $value) { assert(is_int($value) || is_float($value)); }
}
