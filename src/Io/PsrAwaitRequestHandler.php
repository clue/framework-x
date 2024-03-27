<?php

namespace FrameworkX\Io;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use function React\Async\await;
use function React\Promise\resolve;

/**
 * @internal
 */
class PsrAwaitRequestHandler implements RequestHandlerInterface
{

    /**
     * @var callable|null
     */
    private $next;

    public function __construct(callable $next = null) {
        $this->next = $next;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->next === null) {
            return new Response();
        }
        return await(resolve(($this->next)($request)));
    }
}
