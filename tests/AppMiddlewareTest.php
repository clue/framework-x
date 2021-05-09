<?php

namespace FrameworkX\Tests;

use FrameworkX\App;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;

class AppMiddlewareTest extends TestCase
{
    public function testGetMethodThrowsExceptionWhenCalledWithMiddlewareRequestHandler()
    {
        $loop = $this->createMock(LoopInterface::class);
        $app = new App($loop);

        $this->expectException(\BadMethodCallException::class);
        $app->get('/', function () { }, function () { });
    }
}
