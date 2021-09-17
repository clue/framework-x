<?php

namespace FrameworkX;

use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

/**
 * @internal
 */
class ErrorHandler
{
    public function requestNotFound(): ResponseInterface
    {
        return $this->htmlResponse(
            404,
            'Page Not Found',
            'Please check the URL in the address bar and try again.'
        );
    }

    public function requestMethodNotAllowed(array $allowedMethods): ResponseInterface
    {
        $methods = \implode('/', \array_map(function (string $method) { return '<code>' . $method . '</code>'; }, $allowedMethods));

        return $this->htmlResponse(
            405,
            'Method Not Allowed',
            'Please check the URL in the address bar and try again with ' . $methods . ' request.'
        )->withHeader('Allow', \implode(', ', $allowedMethods));
    }

    public function requestProxyUnsupported(): ResponseInterface
    {
        return $this->htmlResponse(
            400,
            'Proxy Requests Not Allowed',
            'Please check your settings and retry.'
        );
    }

    public function errorInvalidException(\Throwable $e): ResponseInterface
    {
        $where = ' in <code title="See ' . $e->getFile() . ' line ' . $e->getLine() . '">' . \basename($e->getFile()) . ':' . $e->getLine() . '</code>';
        $message = '<code>' . $this->escapeHtml($e->getMessage()) . '</code>';

        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got uncaught <code>' . \get_class($e) . '</code> with message ' . $message . $where . '.'
        );
    }

    public function errorInvalidResponse($value): ResponseInterface
    {
        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
        );
    }

    public function errorInvalidCoroutine($value): ResponseInterface
    {
        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to yield <code>' . PromiseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
        );
    }

    private static function htmlResponse(int $statusCode, string $title, string ...$info): ResponseInterface
    {
        $nonce = \base64_encode(\random_bytes(16));
        $info = \implode('', \array_map(function (string $info) { return "<p>$info</p>\n"; }, $info));
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>Error $statusCode: $title</title>
<style nonce="$nonce">
body { display: grid; justify-content: center; align-items: center; grid-auto-rows: minmax(min-content, calc(100vh - 4em)); margin: 2em; font-family: ui-sans-serif, Arial, "Noto Sans", sans-serif; }
@media (min-width: 700px) { main { display: grid; max-width: 700px; } }
h1 { margin: 0 .5em 0 0; border-right: calc(2 * max(0px, min(100vw - 700px + 1px, 1px))) solid #e3e4e7; padding-right: .5em; color: #aebdcc; font-size: 3em; }
strong { color: #111827; font-size: 3em; }
p { margin: .5em 0 0 0; grid-column: 2; color: #6b7280; }
code { padding: 0 .3em; background-color: #f5f6f9; }
</style>
</head>
<body>
<main>
<h1>$statusCode</h1>
<strong>$title</strong>
$info</main>
</body>
</html>

HTML;

        return new Response(
            $statusCode,
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'Content-Security-Policy' => "style-src 'nonce-$nonce'; img-src 'self'; default-src 'none'"
            ],
            $html
        );
    }

    public function describeType($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (\is_scalar($value) && !\is_string($value)) {
            return \var_export($value, true);
        }
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }

    private function escapeHtml(string $s): string
    {
        return \addcslashes(
            \str_replace(
                ' ',
                '&nbsp;',
                \htmlspecialchars($s, \ENT_NOQUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED, 'utf-8')
            ),
            "\0..\032\\"
        );
    }
}
