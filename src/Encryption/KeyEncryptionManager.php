<?php

namespace Chronicle\Encryption;

use Chronicle\Contracts\KeyEncryptionProvider;
use Chronicle\Exceptions\EncryptionException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured KEK provider from chronicle.encryption.kek via the
 * container, so a provider's constructor dependencies (e.g. a KMS client) are
 * auto-injected alongside its `config` array.
 */
final class KeyEncryptionManager
{
    public function __construct(
        private readonly Container $container,
    ) {
        //
    }

    public function provider(): KeyEncryptionProvider
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('chronicle.encryption.kek', []);

        $providerClass = $config['provider'] ?? null;

        if (! is_string($providerClass) || ! is_a($providerClass, KeyEncryptionProvider::class, true)) {
            throw EncryptionException::invalidKekProvider($providerClass);
        }

        /** @var KeyEncryptionProvider */
        return $this->container->makeWith($providerClass, ['config' => $config]);
    }
}
