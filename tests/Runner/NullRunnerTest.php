<?php

namespace FrameworkX\Tests\Runner;

use FrameworkX\Runner\NullRunner;
use PHPUnit\Framework\TestCase;

class NullRunnerTest extends TestCase
{
    public function testInvokeReturnsImmediately(): void
    {
        $runner = new NullRunner();

        $this->expectOutputString('');
        $runner(function () {
            throw new \BadFunctionCallException('Should not be called');
        });
    }
}
