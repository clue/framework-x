<?php

namespace FrameworkX\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

class MethodController
{
    public function index(): ResponseInterface
    {
        return Response::plaintext('index');
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        return Response::plaintext('show ' . $request->getAttribute('id'));
    }

    public function middleware(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);
        
        return $response->withHeader('X-Method-Controller', 'value');
    }
} 