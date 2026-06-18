<?php

declare(strict_types=1);

use Chronicle\Encryption\LocalKeyEncryptionProvider;
use Chronicle\Exceptions\EncryptionException;

function localKek(): LocalKeyEncryptionProvider
{
    return new LocalKeyEncryptionProvider([
        'key' => base64_encode(str_repeat("\x11", SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
        'id' => 'local-test',
    ]);
}

it('round-trips a wrapped DEK', function () {
    $provider = localKek();
    $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

    $wrapped = $provider->wrap($dek);

    expect($wrapped)->not->toBe($dek)
        ->and($provider->unwrap($wrapped))->toBe($dek)
        ->and($provider->kekId())->toBe('local-test');
});

it('produces a different ciphertext each wrap (random nonce)', function () {
    $provider = localKek();
    $dek = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

    expect($provider->wrap($dek))->not->toBe($provider->wrap($dek));
});

it('throws when the encryption key is missing', function () {
    new LocalKeyEncryptionProvider(['key' => null]);
})->throws(EncryptionException::class);

it('throws when the encryption key is the wrong length', function () {
    new LocalKeyEncryptionProvider(['key' => base64_encode('too-short')]);
})->throws(EncryptionException::class);

it('rejects a tampered wrapped DEK', function () {
    $provider = localKek();
    $wrapped = $provider->wrap(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    $raw = base64_decode($wrapped, true);
    $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === "\x00" ? "\x01" : "\x00";

    $provider->unwrap(base64_encode($raw));
})->throws(EncryptionException::class);
