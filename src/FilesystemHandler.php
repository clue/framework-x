<?php

namespace FrameworkX;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class FilesystemHandler
{
    private $root;

    /**
     * Mapping between file extension and MIME type to send in `Content-Type` response header
     *
     * @var array<string,string>
     */
    private $mimetypes = array(
        'atom' => 'application/atom+xml',
        'bz2' => 'application/x-bzip2',
        'css' => 'text/css',
        'gif' => 'image/gif',
        'gz' => 'application/gzip',
        'htm' => 'text/html',
        'html' => 'text/html',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'rss' => 'application/rss+xml',
        'svg' => 'image/svg+xml',
        'tar' => 'application/x-tar',
        'xml' => 'application/xml',
        'zip' => 'application/zip',
    );

    /**
     * Assign default MIME type to send in `Content-Type` response header (same as nginx/Apache)
     *
     * @var string
     * @see self::$mimetypes
     */
    private $defaultMimetype = 'text/plain';

    /** @var ErrorHandler */
    private $errorHandler;

    /** @var HtmlHandler */
    private $html;

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->errorHandler = new ErrorHandler();
        $this->html = new HtmlHandler();
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $local = $request->getAttribute('path', '');
        $path = \rtrim($this->root . '/' . $local, '/');

        // local path should not contain "./", "../", "//" or null bytes or start with slash
        $valid = !\preg_match('#(?:^|/)\.\.?(?:$|/)|^/|//|\x00#', $local);

        \clearstatcache();
        if ($valid && \is_dir($path)) {
            if ($local !== '' && \substr($local, -1) !== '/') {
                return (new RedirectHandler(\basename($path) . '/'))();
            }

            $response = '<strong>' . $this->html->escape($local === '' ? '/' : $local) . '</strong>' . "\n<ul>\n";

            if ($local !== '') {
                $response .= '    <li><a href="../">../</a></li>' . "\n";
            }

            $files = \scandir($path);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $dir = \is_dir($path . '/' . $file) ? '/' : '';
                $response .= '    <li><a href="' . \rawurlencode($file) . $dir . '">' . $this->html->escape($file) . $dir . '</a></li>' . "\n";
            }
            $response .= '</ul>' . "\n";

            return Response::html(
                $response
            );
        } elseif ($valid && \is_file($path)) {
            if ($local !== '' && \substr($local, -1) === '/') {
                return (new RedirectHandler('../' . \basename($path)))();
            }

            // Assign MIME type based on file extension (same as nginx/Apache) or fall back to given default otherwise.
            // Browers are pretty good at figuring out the correct type if no charset attribute is given.
            $ext = \strtolower(\substr($path, \strrpos($path, '.') + 1));
            $headers = [
                'Content-Type' => $this->mimetypes[$ext] ?? $this->defaultMimetype
            ];

            $stat = @\stat($path);
            if ($stat !== false) {
                $headers['Last-Modified'] = \gmdate('D, d M Y H:i:s', $stat['mtime']) . ' GMT';

                if ($request->getHeaderLine('If-Modified-Since') === $headers['Last-Modified']) {
                    return new Response(Response::STATUS_NOT_MODIFIED);
                }
            }

            return new Response(
                Response::STATUS_OK,
                $headers,
                \file_get_contents($path)
            );
        } else {
            return $this->errorHandler->requestNotFound();
        }
    }
}
