<?php

namespace Frugal;

use Psr\Http\Message\ServerRequestInterface;

class RemoveLeadingPath
{
    public function __construct(string $leading, $next)
    {
        $this->leading = $leading;
        $this->next = $next;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $uri = $request->getUri();
        if (\strpos($uri->getPath(), $this->leading) === 0) {
            $request = $request->withUri(
                $uri->withPath('/' . \ltrim(\substr($uri->getPath(), \strlen($this->leading)), '/')),
                true
            );
        }

        $next = $this->next;
        return $next($request);
    }
}
