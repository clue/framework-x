<?php

namespace FrameworkX\Runner;

/**
 * Application runner stub that does nothing when invoked.
 *
 * This experimental application runner can be used in environments where the
 * application lifecycle is managed externally. It can be useful for integration
 * testing or when embedding the application in other systems.
 *
 * Note that this runner is not intended for production use and is not loaded by
 * default. You need to explicitly configure your application to use this runner
 * through the `X_EXPERIMENTAL_RUNNER` environment variable:
 *
 * ```php
 * $container = new Container([
 *     // 'X_EXPERIMENTAL_RUNNER' => fn(?string $X_EXPERIMENTAL_RUNNER = null): ?string => $X_EXPERIMENTAL_RUNNER,
 *     // 'X_EXPERIMENTAL_RUNNER' => fn(bool|string $ACME = false): ?string => $ACME ? NullRunner::class : null,
 *     'X_EXPERIMENTAL_RUNNER' => NullRunner::class
 * ]);
 *
 * $app = new App($container);
 * ```
 *
 * Likewise, you may pass this runner through an environment variable from your
 * integration tests, see also included PHPT test files for examples.
 *
 * @see \FrameworkX\Container::getRunner()
 */
class NullRunner
{
    /**
     * @param callable(\Psr\Http\Message\ServerRequestInterface):(\Psr\Http\Message\ResponseInterface|\React\Promise\PromiseInterface<\Psr\Http\Message\ResponseInterface>) $handler
     * @return void
     */
    public function __invoke(callable $handler): void
    {
        // NO-OP
    }
}
