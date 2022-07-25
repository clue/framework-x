<?php

namespace Framework\Tests\Io;

use FrameworkX\Io\HtmlHandler;
use PHPUnit\Framework\TestCase;

class HtmlHandlerTest extends TestCase
{
    /**
     * @dataProvider provideNames
     * @param string $in
     * @param string $expected
     */
    public function testEscapeHtml(string $in, string $expected)
    {
        $html = new HtmlHandler();

        $this->assertEquals($expected, $html->escape($in));
    }

    public function provideNames()
    {
        return [
            [
                'hello/',
                'hello/'
            ],
            [
                'hellö.txt',
                'hellö.txt'
            ],
            [
                'hello world',
                'hello world'
            ],
            [
                'hello    world',
                'hello &nbsp; &nbsp;world'
            ],
            [
                ' hello world ',
                '&nbsp;hello world&nbsp;'
            ],
            [
                "hello\nworld",
                'hello<span>\n</span>world'
            ],
            [
                "hello\tworld",
                'hello<span>\t</span>world'
            ],
            [
                "hello\\nworld",
                'hello\nworld'
            ],
            [
                'h<e>llo',
                'h&lt;e&gt;llo'
            ],
            [
                "hell\xF6.txt",
                'hell�.txt'
            ],
            [
                "bin\00ary",
                'bin�ary'
            ]
        ];
    }
}
