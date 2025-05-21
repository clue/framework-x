<?php

namespace FrameworkX\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class MethodController
{
    public function index()
    {
        return Response::plaintext('index');
    }

    public function show(ServerRequestInterface $request)
    {
        return Response::plaintext('show ' . $request->getAttribute('id'));
    }

    public function middleware(ServerRequestInterface $request, callable $next)
    {
        $response = $next($request);
        
        return $response->withHeader('X-Method-Controller', 'value');
    }
} 