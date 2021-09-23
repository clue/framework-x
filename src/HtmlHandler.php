<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

/**
 * @internal
 */
class HtmlHandler
{
    public function statusResponse(int $statusCode, string $title, string $subtitle, string $info): ResponseInterface
    {
        $nonce = \base64_encode(\random_bytes(16));
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>$title</title>
<style nonce="$nonce">
body { display: grid; justify-content: center; align-items: center; grid-auto-rows: minmax(min-content, calc(100vh - 4em)); margin: 2em; font-family: ui-sans-serif, Arial, "Noto Sans", sans-serif; }
@media (min-width: 700px) { main { display: grid; max-width: 700px; } }
h1 { margin: 0 .5em 0 0; border-right: calc(2 * max(0px, min(100vw - 700px + 1px, 1px))) solid #e3e4e7; padding-right: .5em; color: #aebdcc; font-size: 3em; }
strong { color: #111827; font-size: 3em; }
p { margin: .5em 0 0 0; grid-column: 2; color: #6b7280; }
code { padding: 0 .3em; background-color: #f5f6f9; }
a { color: inherit; }
</style>
</head>
<body>
<main>
<h1>$statusCode</h1>
<strong>$subtitle</strong>
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

    public function escape(string $s): string
    {
        return \addcslashes(
            \preg_replace(
                '/(^| ) |(?: $)/',
                '$1&nbsp;',
                \htmlspecialchars($s, \ENT_NOQUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED, 'utf-8')
            ),
            "\0..\032\\"
        );
    }
}
