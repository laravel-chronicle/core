<?php

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Exceptions\EncryptionException;

it('serializes to a self-describing array', function () {
    $env = new CipherEnvelope('bm9uY2U=', 'Y2lwaGVy');

    expect($env->toArray())->toBe([
        '_chronicle_enc' => 'v1',
        'nonce' => 'bm9uY2U=',
        'ciphertext' => 'Y2lwaGVy',
    ]);
});

it('round-trips through fromArray', function () {
    $env = CipherEnvelope::fromArray([
        '_chronicle_enc' => 'v1',
        'nonce' => 'bm9uY2U=',
        'ciphertext' => 'Y2lwaGVy',
    ]);

    expect($env->version)->toBe('v1')
        ->and($env->nonce)->toBe('bm9uY2U=')
        ->and($env->ciphertext)->toBe('Y2lwaGVy');
});

it('detects envelopes via the marker', function () {
    expect(CipherEnvelope::isEnvelope(['_chronicle_enc' => 'v1', 'nonce' => 'a', 'ciphertext' => 'b']))->toBeTrue()
        ->and(CipherEnvelope::isEnvelope(['foo' => 'bar']))->toBeFalse();
});

it('rejects a non-envelope array', function () {
    CipherEnvelope::fromArray(['foo' => 'bar']);
})->throws(EncryptionException::class);

it('rejects a malformed envelope', function () {
    CipherEnvelope::fromArray(['_chronicle_enc' => 'v1', 'nonce' => 123]);
})->throws(EncryptionException::class);
