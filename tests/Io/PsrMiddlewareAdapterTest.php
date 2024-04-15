<?php

namespace FrameworkX\Tests\Io;

use FrameworkX\Io\PsrMiddlewareAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Async\await;

class PsrMiddlewareAdapterTest extends TestCase
{

    public function testInvokeCallsPsrMiddleware(): void
    {
        $response = new Response(200, [], '');
        $deferred = new Deferred();
        $deferred->resolve($response);

        $psrMiddleware = new class ($response) implements MiddlewareInterface {

            /**
             * @var Response
             */
            private $response;

            public function __construct(Response $response)
            {
                $this->response = $response;
            }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $this->response;
            }
        };

        $handler = new PsrMiddlewareAdapter($psrMiddleware);

        $request = new ServerRequest('GET', 'http://localhost/');
        /** @var PromiseInterface<ResponseInterface> $responsePromise */
        $responsePromise = $handler($request);
        $this->assertSame($response, await($responsePromise));
    }
}
