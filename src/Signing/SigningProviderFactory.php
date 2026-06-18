<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

final class SigningProviderFactory
{
    public function __construct(
        private readonly Container $container,
    ) {
        //
    }

    /**
     * Build a SigningProvider for the given ring key ID and config array.
     *
     * The provider class is resolved via the container so any constructor
     * dependencies (e.g. SDK clients) are auto-injected alongside `config`.
     *
     * @param  array<string, mixed>  $keyConfig
     *
     * @throws RuntimeException when provider class is missing or invalid
     */
    public function make(string $id, array $keyConfig): SigningProvider
    {
        $providerClass = $keyConfig['provider'] ?? null;

        if (! is_string($providerClass)) {
            throw new RuntimeException(
                'Chronicle signing provider ['.get_debug_type($providerClass).'] must implement '.SigningProvider::class.'.'
            );
        }

        if (! is_a($providerClass, SigningProvider::class, true)) {
            throw new RuntimeException(
                'Chronicle signing provider ['.$providerClass.'] must implement '.SigningProvider::class.'.'
            );
        }

        $keyConfig['key_id'] = $id;

        /** @var SigningProvider */
        return $this->container->makeWith($providerClass, ['config' => $keyConfig]);
    }
}
