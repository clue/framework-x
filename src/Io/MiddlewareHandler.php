<?php

namespace FrameworkX\Io;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Middleware\StreamingRequestMiddleware;

/**
 * @internal
 */
class MiddlewareHandler
{
    private $handlers;
    private $hastStreamingRequest = false;

    public function __construct(array $handlers)
    {
        assert(count($handlers) >= 2);

        $this->handlers = $handlers;
        foreach($this->handlers as $handler) {
            if ($this->hastStreamingRequest = ($handler instanceof StreamingRequestMiddleware)) {
                break;
            }
        }
    }

    public function __invoke(ServerRequestInterface $request)
    {
        return $this->call($request, 0);
    }

    private function call(ServerRequestInterface $request, int $position)
    {
        if (!isset($this->handlers[$position + 2])) {
            return $this->handlers[$position]($request, $this->handlers[$position + 1]);
        }

        return $this->handlers[$position]($request, function (ServerRequestInterface $request) use ($position) {
            return $this->call($request, $position + 1);
        });
    }

    public function hastStreamingRequest() {
        return $this->hastStreamingRequest;
    }
}
