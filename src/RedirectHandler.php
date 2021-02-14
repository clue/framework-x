<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

/**
 * @internal
 */
class RedirectHandler
{
    private $target;
    private $code;

    public function __construct(string $target, int $code = 302)
    {
        $this->target = $target;
        $this->code = $code;
    }

    public function __invoke(): ResponseInterface
    {
        return new Response(
            $this->code,
            [
                'Content-Type' => 'text/html',
                'Location' => $this->target
            ],
            'See ' . $this->target . '...' . "\n"
        );
    }
}
