<?php

namespace Frugal;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;

class FsHandler
{
    public function __construct(string $root)
    {
        $this->root = \rtrim($root, '/');
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $path = $this->root . $request->getUri()->getPath();

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

            $response = '<strong>' . $this->escape($path) . '</strong>' . "\n<ul>\n";

            $files = \scandir($path);
            foreach ($files as $file) {
                if (\is_dir($path . '/' . $file)) {
                    $file .= '/';
                }
                $response .= '    <li><a href="' . $this->escape($file) . '">' . $this->escape($file) . '</a></li>' . "\n";
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
                \fopen($path, 'r')
            );
        } else {
            return new Response(
                404,
                [],
                $path
            );
        }
    }

    private function escape(string $s)
    {
        return \htmlspecialchars($s, null, 'utf-8');
    }
}
