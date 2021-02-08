<?php

namespace Frugal;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class FilesystemHandler
{
    private $root;

    public function __construct(string $root)
    {
        $this->root = \rtrim($root, '/');
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $local = $request->getAttribute('path', '');
        $path = $this->root . '/' . $local;

        // local path should not contain "./", "../", "//" or null bytes or start with slash
        $valid = !\preg_match('#(?:^|/)..?(?:$|/)|^/|//|\x00#', $local);

        \clearstatcache();
        if ($valid && \is_dir($path)) {
            if (\substr($path, -1) !== '/') {
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
        } elseif ($valid && \is_file(\rtrim($path, '/'))) {
            if (\substr($path, -1) === '/') {
                return new Response(
                    302,
                    [
                        'Location' => '../' . \basename($path)
                    ]
                );
            }


//             $header = [];
//             if (substr($path, -4) === '.txt') {
//                 $header = ['Content-Type' => 'text/plain'];
//             } elseif (substr($path, -4) === '.php') {
                $header = ['Content-Type' => 'text/plain; charset=utf-8'];
//             }

            return new Response(
                200,
                $header,
                \file_get_contents($path)
            );
        } else {
            return new Response(
                404,
                [
                    'Content-Type' => 'text/plain; charset=utf-8'
                ],
                "File not found: " . $this->escapeText($local) . "\n"
            );
        }
    }

    private function escapeText(string $s): string
    {
        return \htmlspecialchars_decode($this->escapeHtml($s));
    }

    private function escapeHtml(string $s): string
    {
        return \htmlspecialchars($s, \ENT_NOQUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED, 'utf-8');
    }
}
