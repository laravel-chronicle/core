<?php

declare(strict_types=1);

use Chronicle\Encryption\KeyEncryptionManager;
use Chronicle\Encryption\LocalKeyEncryptionProvider;
use Chronicle\Encryption\SubjectKey;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Exceptions\EncryptionException;

beforeEach(function () {
    config()->set('chronicle.encryption.kek', [
        'provider' => LocalKeyEncryptionProvider::class,
        'key' => base64_encode(str_repeat("\x33", SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
        'id' => 'local',
    ]);
});

it('creates, wraps and persists a DEK on first use', function () {
    $manager = app(SubjectKeyManager::class);

    $dek = $manager->getOrCreate('App\\Models\\User', '1');

    expect(strlen($dek))->toBe(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

    $row = SubjectKey::query()->where('subject_id', '1')->firstOrFail();
    expect($row->status)->toBe('active')
        ->and($row->wrapped_dek)->not->toBeNull()
        ->and($row->wrapped_dek)->not->toContain(base64_encode($dek)) // never store plaintext DEK
        ->and($row->kek_id)->toBe('local');
});

it('returns the same DEK on subsequent calls and does not create a second row', function () {
    $manager = app(SubjectKeyManager::class);

    $first = $manager->getOrCreate('App\\Models\\User', '1');
    $second = $manager->getOrCreate('App\\Models\\User', '1');

    expect($second)->toBe($first)
        ->and(SubjectKey::query()->where('subject_id', '1')->count())->toBe(1);
});

it('unwraps a persisted DEK in a fresh manager (no shared cache)', function () {
    app(SubjectKeyManager::class)->getOrCreate('App\\Models\\User', '1');

    // A brand-new manager instance has an empty process-local cache.
    $fresh = new SubjectKeyManager(app(KeyEncryptionManager::class));

    expect(strlen($fresh->getOrCreate('App\\Models\\User', '1')))
        ->toBe(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
});

it('destroys the DEK: nulls wrapped_dek, tombstones the row, purges cache', function () {
    $manager = app(SubjectKeyManager::class);
    $manager->getOrCreate('App\\Models\\User', '1');

    $manager->destroy('App\\Models\\User', '1');

    $row = SubjectKey::query()->where('subject_id', '1')->firstOrFail();
    expect($row->isErased())->toBeTrue()
        ->and($row->wrapped_dek)->toBeNull()
        ->and($row->erased_at)->not->toBeNull();
});

it('does NOT resurrect an erased subject (erased stays erased)', function () {
    $manager = app(SubjectKeyManager::class);
    $manager->getOrCreate('App\\Models\\User', '1');
    $manager->destroy('App\\Models\\User', '1');

    $manager->getOrCreate('App\\Models\\User', '1');
})->throws(EncryptionException::class);

it('is idempotent: destroying twice is a no-op', function () {
    $manager = app(SubjectKeyManager::class);
    $manager->getOrCreate('App\\Models\\User', '1');

    $manager->destroy('App\\Models\\User', '1');
    $manager->destroy('App\\Models\\User', '1');

    expect(SubjectKey::query()->where('subject_id', '1')->count())->toBe(1);
});

it('tombstones a never-seen subject so it can never mint a key', function () {
    $manager = app(SubjectKeyManager::class);

    $manager->destroy('App\\Models\\User', '99');

    $row = SubjectKey::query()->where('subject_id', '99')->firstOrFail();
    expect($row->isErased())->toBeTrue()
        ->and($row->wrapped_dek)->toBeNull()
        ->and(fn () => $manager->getOrCreate('App\\Models\\User', '99'))
        ->toThrow(EncryptionException::class);
});
