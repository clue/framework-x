<?php

namespace Framework\Tests;

use FrameworkX\RedirectHandler;
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
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('http://example.com/', $response->getHeaderLine('Location'));
        $this->assertEquals("See http://example.com/...\n", (string ) $response->getBody());
    }

    public function testInvokeReturnsResponseWithPermanentRedirectToGivenLocationAndCode()
    {
        $handler = new RedirectHandler('/', 301);

        $response = $handler();

        /** @var ResponseInterface $response */
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('text/html', $response->getHeaderLine('Content-Type'));

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/', $response->getHeaderLine('Location'));
        $this->assertEquals("See /...\n", (string ) $response->getBody());
    }
}
