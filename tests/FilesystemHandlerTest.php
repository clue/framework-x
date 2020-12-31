<?php

use PHPUnit\Framework\TestCase;
use Frugal\FilesystemHandler;
use React\Http\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;

class FilesystemHandlerTest extends TestCase
{
    public function testInvokeWithValidPathToFileWillReturnResponseWithFileContents()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/LICENSE');
        $request = $request->withAttribute('path', 'LICENSE');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals(file_get_contents(__DIR__ . '/../LICENSE'), (string) $response->getBody());
    }

    public function testInvokeWithInvalidPathWillReturnFileNotFoundResponse()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/invalid');
        $request = $request->withAttribute('path', 'invalid');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals("File not found: invalid\n", (string) $response->getBody());
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
        $this->assertEquals("<strong>.github/</strong>\n<ul>\n    <li><a href=\"../\">../</a></li>\n    <li><a href=\"workflows/\">workflows/</a></li>\n</ul>\n", (string) $response->getBody());
    }

    public function testInvokeWithValidPathToDirectoryButWithoutTrailingSlashWillReturnRedirectToPathWithSlash()
    {
        $handler = new FilesystemHandler(dirname(__DIR__));

        $request = new ServerRequest('GET', '/source/.github');
        $request = $request->withAttribute('path', '.github');

        $response = $handler($request);

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('.github/', $response->getHeaderLine('Location'));
    }
}
