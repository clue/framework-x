<?php

namespace FrameworkX\Tests;

use FrameworkX\FilesystemHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\ServerRequest;

class FilesystemHandlerTest extends TestCase
{
    public function testInvokeWithValidPathToComposerJsonWillReturnResponseWithFileContentsAndContentType()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/composer.json');
        $request = $request->withAttribute('path', 'composer.json');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/../composer.json'), (string) $response->getBody());
    }

    public function testInvokeWithValidPathToLicenseWillReturnResponseWithFileContentsAndDefaultContentType()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/LICENSE');
        $request = $request->withAttribute('path', 'LICENSE');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/../LICENSE'), (string) $response->getBody());
    }

    public function testInvokeWithValidPathToOneCharacterFilenameWillReturnResponseWithFileContentsAndDefaultContentType()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/tests/data/a');
        $request = $request->withAttribute('path', 'tests/data/a');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/data/a'), (string) $response->getBody());
    }

    public function testInvokeWithValidPathToTwoCharacterFilenameWillReturnResponseWithFileContentsAndDefaultContentType()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/tests/data/b');
        $request = $request->withAttribute('path', 'tests/data/bb');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/data/bb'), (string) $response->getBody());
    }

    public function testInvokeWithValidPathToComposerJsonAndCachingHeaderWillReturnResponseNotModifiedWithoutContents()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/composer.json');
        $request = $request->withAttribute('path', 'composer.json');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $response = $handler($request->withHeader('If-Modified-Since', $response->getHeaderLine('Last-Modified')));

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(304, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertFalse($response->hasHeader('Last-Modified'));
        $this->assertEquals('', (string) $response->getBody());
    }

    public function testInvokeWithInvalidPathWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/invalid');
        $request = $request->withAttribute('path', 'invalid');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithDoubleSlashWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/LICENSE//');
        $request = $request->withAttribute('path', 'LICENSE//');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithPathWithLeadingSlashWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source//LICENSE');
        $request = $request->withAttribute('path', '/LICENSE');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithPathWithDotSegmentWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/./LICENSE');
        $request = $request->withAttribute('path', './LICENSE');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithPathBelowRootWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(__DIR__);

        $request = new ServerRequest('GET', '/source/../LICENSE');
        $request = $request->withAttribute('path', '../LICENSE');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithBinaryPathWillReturnNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/invalid');
        $request = $request->withAttribute('path', "bin\x00ary");

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("<title>Error 404: Page Not Found</title>\n", (string) $response->getBody());
    }

    public function testInvokeWithoutPathWillReturnResponseWithDirectoryListing()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<strong>/</strong>', (string) $response->getBody());
        $this->assertStringContainsString('<a href=".github/">.github/</a>', (string) $response->getBody());
        $this->assertStringNotContainsString('<a href="../">../</a>', (string) $response->getBody());
    }

    public function testInvokeWithEmptyPathWillReturnResponseWithDirectoryListing()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/');
        $request = $request->withAttribute('path', '');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<strong>/</strong>', (string) $response->getBody());
        $this->assertStringContainsString('<a href=".github/">.github/</a>', (string) $response->getBody());
        $this->assertStringNotContainsString('<a href="../">../</a>', (string) $response->getBody());
    }

    public function testInvokeWithoutPathAndRootIsFileWillReturnResponseWithFileContents()
    {
        $handler = new FilesystemHandler(dirname(__DIR__) . '/LICENSE');

        $request = new ServerRequest('GET', '/source/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/../LICENSE'), (string) $response->getBody());
    }

    public function testInvokeWithValidPathToDirectoryWillReturnResponseWithDirectoryListing()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/.github/');
        $request = $request->withAttribute('path', '.github/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("<strong>.github/</strong>\n<ul>\n    <li><a href=\"../\">../</a></li>\n    <li><a href=\"FUNDING.yml\">FUNDING.yml</a></li>\n    <li><a href=\"ISSUE_TEMPLATE/\">ISSUE_TEMPLATE/</a></li>\n    <li><a href=\"workflows/\">workflows/</a></li>\n</ul>\n", (string) $response->getBody());
    }

    public function testInvokeWithValidPathToDirectoryButWithoutTrailingSlashWillReturnRedirectToPathWithSlash()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/.github');
        $request = $request->withAttribute('path', '.github');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('.github/', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to .github/</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\".github/\"><code>.github/</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testInvokeWithValidPathToFileButWithTrailingSlashWillReturnRedirectToPathWithoutSlash()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/LICENSE/');
        $request = $request->withAttribute('path', 'LICENSE/');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('../LICENSE', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to ../LICENSE</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"../LICENSE\"><code>../LICENSE</code></a>...</p>\n", (string) $response->getBody());
    }
}
