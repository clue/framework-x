<?php

namespace FrameworkX;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;

/**
 * @internal
 */
class ErrorHandler
{
    /** @var Htmlhandler */
    private $html;

    public function __construct()
    {
        $this->html = new HtmlHandler();
    }

    /**
     * @return ResponseInterface|PromiseInterface<ResponseInterface,void>|\Generator
     *     Returns a response, a Promise which eventually fulfills with a
     *     response or a Generator which eventually returns a response. This
     *     method never throws or resolves a rejected promise. If the next
     *     handler fails to return a valid response, it will be turned into a
     *     valid error response before returning.
     */
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            return $this->errorInvalidException($e);
        }

        if ($response instanceof ResponseInterface) {
            return $response;
        } elseif ($response instanceof PromiseInterface) {
            return $response->then(function ($response) {
                if ($response instanceof ResponseInterface) {
                    return $response;
                } else {
                    return $this->errorInvalidResponse($response);
                }
            }, function ($e) {
                if ($e instanceof \Throwable) {
                    return $this->errorInvalidException($e);
                } else {
                    return $this->errorInvalidResponse(\React\Promise\reject($e));
                }
            });
        } elseif ($response instanceof \Generator) {
            return $this->coroutine($response);
        } else {
            return $this->errorInvalidResponse($response);
        }
    }

    private function coroutine(\Generator $generator): \Generator
    {
        do {
            try {
                if (!$generator->valid()) {
                    $response = $generator->getReturn();
                    if ($response instanceof ResponseInterface) {
                        return $response;
                    } else {
                        return $this->errorInvalidResponse($response);
                    }
                }
            } catch (\Throwable $e) {
                return $this->errorInvalidException($e);
            }

            $promise = $generator->current();
            if (!$promise instanceof PromiseInterface) {
                return $this->errorInvalidCoroutine($promise);
            }

            try {
                $next = yield $promise;
            } catch (\Throwable $e) {
                try {
                    $generator->throw($e);
                    continue;
                } catch (\Throwable $e) {
                    return $this->errorInvalidException($e);
                }
            }

            try {
                $generator->send($next);
            } catch (\Throwable $e) {
                return $this->errorInvalidException($e);
            }
        } while (true);
    } // @codeCoverageIgnore

    public function requestNotFound(): ResponseInterface
    {
        return $this->htmlResponse(
            404,
            'Page Not Found',
            'Please check the URL in the address bar and try again.'
        );
    }

    public function requestMethodNotAllowed(array $allowedMethods): ResponseInterface
    {
        $methods = \implode('/', \array_map(function (string $method) { return '<code>' . $method . '</code>'; }, $allowedMethods));

        return $this->htmlResponse(
            405,
            'Method Not Allowed',
            'Please check the URL in the address bar and try again with ' . $methods . ' request.'
        )->withHeader('Allow', \implode(', ', $allowedMethods));
    }

    public function requestProxyUnsupported(): ResponseInterface
    {
        return $this->htmlResponse(
            400,
            'Proxy Requests Not Allowed',
            'Please check your settings and retry.'
        );
    }

    public function errorInvalidException(\Throwable $e): ResponseInterface
    {
        $where = ' in <code title="See ' . $e->getFile() . ' line ' . $e->getLine() . '">' . \basename($e->getFile()) . ':' . $e->getLine() . '</code>';
        $message = '<code>' . $this->html->escape($e->getMessage()) . '</code>';

        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got uncaught <code>' . \get_class($e) . '</code> with message ' . $message . $where . '.'
        );
    }

    public function errorInvalidResponse($value): ResponseInterface
    {
        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to return <code>' . ResponseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
        );
    }

    public function errorInvalidCoroutine($value): ResponseInterface
    {
        return $this->htmlResponse(
            500,
            'Internal Server Error',
            'The requested page failed to load, please try again later.',
            'Expected request handler to yield <code>' . PromiseInterface::class . '</code> but got <code>' . $this->describeType($value) . '</code>.'
        );
    }

    private function htmlResponse(int $statusCode, string $title, string ...$info): ResponseInterface
    {
        return $this->html->statusResponse(
            $statusCode,
            'Error ' . $statusCode . ': ' .$title,
            $title,
            \implode('', \array_map(function (string $info) { return "<p>$info</p>\n"; }, $info))
        );
    }

    public function describeType($value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (\is_scalar($value) && !\is_string($value)) {
            return \var_export($value, true);
        }
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }
}
