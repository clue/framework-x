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
    private $reason;

    /** @var HtmlHandler */
    private $html;

    public function __construct(string $target, int $redirectStatusCode = Response::STATUS_FOUND)
    {
        if ($redirectStatusCode < 300 || $redirectStatusCode === Response::STATUS_NOT_MODIFIED || $redirectStatusCode >= 400) {
            throw new \InvalidArgumentException('Invalid redirect status code given');
        }

        $this->target = $target;
        $this->code = $redirectStatusCode;
        $this->reason = \ucwords((new Response($redirectStatusCode))->getReasonPhrase()) ?: 'Redirect';
        $this->html = new HtmlHandler();
    }

    public function __invoke(): ResponseInterface
    {
        $url = $this->html->escape($this->target);

        return $this->html->statusResponse(
            $this->code,
            'Redirecting to ' . $url,
            $this->reason,
            "<p>Redirecting to <a href=\"$url\"><code>$url</code></a>...</p>\n"
        )->withHeader('Location', $this->target);
    }
}
