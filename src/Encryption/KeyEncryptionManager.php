<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Contracts\KeyEncryptionProvider;
use Chronicle\Exceptions\EncryptionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;

/**
 * Resolves the configured KEK provider from chronicle.encryption.kek via the
 * container, so a provider's constructor dependencies (e.g. a KMS client) are
 * auto-injected alongside its `config` array.
 */
final readonly class KeyEncryptionManager
{
    public function __construct(
        protected Container $container,
    ) {
        //
    }

    public function provider(): KeyEncryptionProvider
    {
        /** @var array<string, mixed> $config */
        $config = Config::array('chronicle.encryption.kek', []);

        return $this->providerFor($config);
    }

    /**
     * Build a provider from an explicit KEK config block. Used by KEK rotation
     * to construct the OLD provider while the container/config hold the NEW KEK.
     *
     * @param  array<string, mixed>  $config
     */
    public function providerFor(array $config): KeyEncryptionProvider
    {
        $providerClass = $config['provider'] ?? null;

        if (! is_string($providerClass) || ! is_a($providerClass, KeyEncryptionProvider::class, true)) {
            throw EncryptionException::invalidKekProvider($providerClass);
        }

        /** @var KeyEncryptionProvider */
        return $this->container->makeWith($providerClass, ['config' => $config]);
    }
}
