<?php

namespace FrameworkX\Tests\Stub;

class InvalidHandlerStub
{
    public function __invoke()
    {
        return null;
    }

    public function index()
    {
        return null;
    }

    public static function static()
    {
        return null;
    }
}
