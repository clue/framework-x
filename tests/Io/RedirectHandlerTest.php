<?php

namespace Framework\Tests\Io;

use FrameworkX\Io\RedirectHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class RedirectHandlerTest extends TestCase
{
    public function testInvokeReturnsResponseWithRedirectToGivenLocation()
    {
        $handler = new RedirectHandler('http://example.com/');

        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringMatchesFormat("<!DOCTYPE html>\n<html>%a</html>\n", (string) $response->getBody());
        $this->assertStringMatchesFormat("%a<style nonce=\"%s\">\n%a", (string) $response->getBody());
        $this->assertStringMatchesFormat('style-src \'nonce-%s\'; img-src \'self\'; default-src \'none\'', $response->getHeaderLine('Content-Security-Policy'));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://example.com/', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to http://example.com/</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<h1>302</h1>\n", (string) $response->getBody());
        $this->assertStringContainsString("<strong>Found</strong>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"http://example.com/\"><code>http://example.com/</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testInvokeReturnsResponseWithPermanentRedirectToGivenLocationAndCode()
    {
        $handler = new RedirectHandler('/', 301);

        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to /</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<h1>301</h1>\n", (string) $response->getBody());
        $this->assertStringContainsString("<strong>Moved Permanently</strong>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"/\"><code>/</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testInvokeReturnsResponseWithCustomRedirectStatusCodeAndGivenLocation()
    {
        $handler = new RedirectHandler('/', 399);

        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->assertEquals(399, $response->getStatusCode());
        $this->assertEquals('/', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to /</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<h1>399</h1>\n", (string) $response->getBody());
        $this->assertStringContainsString("<strong>Redirect</strong>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"/\"><code>/</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testInvokeReturnsResponseWithRedirectToGivenLocationWithSpecialCharsEncoded()
    {
        $handler = new RedirectHandler('/hello%20w%7Frld?a=1&b=2');

        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/hello%20w%7Frld?a=1&b=2', $response->getHeaderLine('Location'));
        $this->assertStringContainsString("<title>Redirecting to /hello%20w%7Frld?a=1&amp;b=2</title>\n", (string) $response->getBody());
        $this->assertStringContainsString("<h1>302</h1>\n", (string) $response->getBody());
        $this->assertStringContainsString("<strong>Found</strong>\n", (string) $response->getBody());
        $this->assertStringContainsString("<p>Redirecting to <a href=\"/hello%20w%7Frld?a=1&amp;b=2\"><code>/hello%20w%7Frld?a=1&amp;b=2</code></a>...</p>\n", (string) $response->getBody());
    }

    public function testConstructWithSuccessCodeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RedirectHandler('/', 200);
    }

    public function testConstructWithNotModifiedCodeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RedirectHandler('/', 304);
    }

    public function testConstructWithBadRequestCodeThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RedirectHandler('/', 400);
    }
}
