<?php

use Chronicle\Contracts\KeyEncryptionProvider;
use Chronicle\Encryption\KeyEncryptionManager;
use Chronicle\Encryption\LocalKeyEncryptionProvider;
use Chronicle\Exceptions\EncryptionException;

/**
 * A KMS-shaped provider: __construct(array $config, SomeClient $client) - the
 * exact shape issue 13 resolves via container makeWith. Proves the manager
 * can build providers with auto-injected constructor dependencies.
 */
class FakeKmsClient
{
    public function id(): string
    {
        return 'kms-key-1';
    }
}

class FakeKmsKeyEncryptionProvider implements KeyEncryptionProvider
{
    /** @param array<string,mixed> $config */
    public function __construct(public array $config, public FakeKmsClient $client) {}

    public function wrap(string $dek): string
    {
        return 'kms:'.base64_encode($dek);
    }

    public function unwrap(string $wrapped): string
    {
        return base64_decode(substr($wrapped, 4), true) ?: '';
    }

    public function kekId(): string
    {
        return $this->client->id();
    }
}

it('resolves the configured local provider', function () {
    config()->set('chronicle.encryption.kek', [
        'provider' => LocalKeyEncryptionProvider::class,
        'key' => base64_encode(str_repeat("\x22", SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
        'id' => 'local',
    ]);

    $provider = app(KeyEncryptionManager::class)->provider();

    expect($provider)->toBeInstanceOf(LocalKeyEncryptionProvider::class)
        ->and($provider->kekId())->toBe('local');
});

it('resolves a KMS-shaped provider, auto-injecting its client dependency', function () {
    config()->set('chronicle.encryption.kek', [
        'provider' => FakeKmsKeyEncryptionProvider::class,
        'region' => 'eu-west-1',
    ]);

    $provider = app(KeyEncryptionManager::class)->provider();

    expect($provider)->toBeInstanceOf(FakeKmsKeyEncryptionProvider::class)
        ->and($provider->kekId())->toBe('kms-key-1');
});

it('throws when the provider class is not a KeyEncryptionProvider', function () {
    config()->set('chronicle.encryption.kek', ['provider' => stdClass::class]);

    app(KeyEncryptionManager::class)->provider();
})->throws(EncryptionException::class);
