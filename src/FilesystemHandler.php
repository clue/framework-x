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

        \clearstatcache();
        if (\is_dir($path)) {
            if (\substr($path, -1) !== '/') {
                return new Response(
                    302,
                    [
                        'Location' => basename($path) . '/'
                    ]
                );
            }

            $response = '<strong>' . $this->escape($local === '' ? '/' : $local) . '</strong>' . "\n<ul>\n";

            if ($local !== '') {
                $response .= '    <li><a href="../">../</a></li>' . "\n";
            }

            $files = \scandir($path);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $dir = \is_dir($path . '/' . $file) ? '/' : '';
                $response .= '    <li><a href="' . $this->escape($file) . $dir . '">' . $this->escape($file) . $dir . '</a></li>' . "\n";
            }
            $response .= '</ul>' . "\n";

            return new Response(
                200,
                [
                    'Content-Type' => 'text/html; charset=utf-8'
                ],
                $response
            );
        } elseif (\is_file($path)) {
            if (false && $this->xAccelSupported) {
                return new Response(
                    200,
                    [
                        'X-Accel-Redirect' => $path
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
                "File not found: " . $local . "\n"
            );
        }
    }

    private function escape(string $s)
    {
        return \htmlspecialchars($s, null, 'utf-8');
    }
}
