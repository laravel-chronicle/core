<?php

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Encryption\PayloadCipher;
use Chronicle\Exceptions\EncryptionException;
use Chronicle\Support\CanonicalPayloadSerializer;

function cipher(): PayloadCipher
{
    return new PayloadCipher(new CanonicalPayloadSerializer);
}

function dek(): string
{
    return random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
}

function aad(): string
{
    return PayloadCipher::aad('01J0ID', 'App\\Models\\User', '1', 'user.updated', 5);
}

it('round-trips a field set', function () {
    $cipher = cipher();
    $dek = dek();
    $aad = aad();
    $fields = ['metadata' => ['ip' => '10.0.0.1'], 'context' => ['env' => 'prod']];

    $envelope = $cipher->encrypt($fields, $dek, $aad);

    // toEqual (==), not toBe (===): CanonicalPayloadSerializer ksorts keys, so
    // the decrypted array is value-equal but key-reordered vs the input.
    expect($envelope)->toBeInstanceOf(CipherEnvelope::class)
        ->and($cipher->decrypt($envelope, $dek, $aad))->toEqual($fields);
});

it('uses a fresh 192-bit nonce per call', function () {
    $cipher = cipher();
    $dek = dek();
    $aad = aad();

    $a = $cipher->encrypt(['metadata' => ['x' => 1]], $dek, $aad);
    $b = $cipher->encrypt(['metadata' => ['x' => 1]], $dek, $aad);

    $nonce = base64_decode($a->nonce, true);
    expect(strlen($nonce))->toBe(24) // 192 bits
        ->and($a->nonce)->not->toBe($b->nonce)
        ->and($a->ciphertext)->not->toBe($b->ciphertext);
});

it('fails to decrypt with the wrong DEK', function () {
    $cipher = cipher();
    $aad = aad();
    $envelope = $cipher->encrypt(['metadata' => ['x' => 1]], dek(), $aad);

    $cipher->decrypt($envelope, dek(), $aad);
})->throws(EncryptionException::class);

it('fails to decrypt when the AAD does not match (transplant defence)', function () {
    $cipher = cipher();
    $dek = dek();
    $envelope = $cipher->encrypt(['metadata' => ['x' => 1]], $dek, aad());

    $otherAad = PayloadCipher::aad('01J0ID', 'App\\Models\\User', '1', 'user.updated', 6);
    $cipher->decrypt($envelope, $dek, $otherAad);
})->throws(EncryptionException::class);

it('fails to decrypt tampered ciphertext', function () {
    $cipher = cipher();
    $dek = dek();
    $aad = aad();
    $envelope = $cipher->encrypt(['metadata' => ['x' => 1]], $dek, $aad);

    $raw = base64_decode($envelope->ciphertext, true);
    $raw[0] = $raw[0] === "\x00" ? "\x01" : "\x00";
    $tampered = new CipherEnvelope($envelope->nonce, base64_encode($raw));

    $cipher->decrypt($tampered, $dek, $aad);
})->throws(EncryptionException::class);

it('rejects a DEK of the wrong length', function () {
    cipher()->encrypt(['metadata' => ['x' => 1]], 'short', aad());
})->throws(EncryptionException::class);

it('decrypts a known XChaCha20-Poly1305-IETF vector (pins the primitive)', function () {
    $cipher = cipher();
    $dek = str_repeat("\x44", SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
    $aad = aad();
    $nonce = str_repeat("\x01", SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $message = (new CanonicalPayloadSerializer)->serialize(['metadata' => ['x' => 1]]);

    $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($message, $aad, $nonce, $dek);
    $envelope = new CipherEnvelope(base64_encode($nonce), base64_encode($ct));

    expect($cipher->decrypt($envelope, $dek, $aad))->toBe(['metadata' => ['x' => 1]]);
});

it('builds a deterministic AAD', function () {
    expect(PayloadCipher::aad('id1', 'T', '1', 'a', 2))
        ->toBe(PayloadCipher::aad('id1', 'T', '1', 'a', 2))
        ->and(PayloadCipher::aad('id1', 'T', '1', 'a', 2))
        ->not->toBe(PayloadCipher::aad('id1', 'T', '1', 'a', 3));
});
