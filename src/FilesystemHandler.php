<?php

namespace Frugal;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class FilesystemHandler
{
    private $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $local = $request->getAttribute('path', '');
        $path = \rtrim($this->root . '/' . $local, '/');

        // local path should not contain "./", "../", "//" or null bytes or start with slash
        $valid = !\preg_match('#(?:^|/)..?(?:$|/)|^/|//|\x00#', $local);

        \clearstatcache();
        if ($valid && \is_dir($path)) {
            if ($local !== '' && \substr($local, -1) !== '/') {
                return new Response(
                    302,
                    [
                        'Location' => \basename($path) . '/'
                    ]
                );
            }

            $response = '<strong>' . $this->escapeHtml($local === '' ? '/' : $local) . '</strong>' . "\n<ul>\n";

            if ($local !== '') {
                $response .= '    <li><a href="../">../</a></li>' . "\n";
            }

            $files = \scandir($path);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $dir = \is_dir($path . '/' . $file) ? '/' : '';
                $response .= '    <li><a href="' . \rawurlencode($file) . $dir . '">' . $this->escapeHtml($file) . $dir . '</a></li>' . "\n";
            }
            $response .= '</ul>' . "\n";

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html; charset=utf-8'
                ],
                $response
            );
        } elseif ($valid && \is_file($path)) {
            if ($local !== '' && \substr($local, -1) === '/') {
                return new Response(
                    302,
                    [
                        'Location' => '../' . \basename($path)
                    ]
                );
            }

            // Assign default MIME type here (same as nginx/Apache).
            // Should use mime database in the future with fallback to given default.
            // Browers are pretty good at figuring out the correct type if no charset attribute is given.
            $headers = [
                'Content-Type' => 'text/plain'
            ];

            $stat = @\stat($path);
            if ($stat !== false) {
                $headers['Last-Modified'] = \gmdate('D, d M Y H:i:s', $stat['mtime']) . ' GMT';

                if ($request->getHeaderLine('If-Modified-Since') === $headers['Last-Modified']) {
                    return new Response(304);
                }
            }

            return new Response(
                200,
                $headers,
                \file_get_contents($path)
            );
        } else {
            return new Response(
                404,
                [
                    'Content-Type' => 'text/plain; charset=utf-8'
                ],
                "Error 404: Not Found\n"
            );
        }
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
