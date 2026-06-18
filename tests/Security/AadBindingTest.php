<?php

declare(strict_types=1);

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Encryption\PayloadCipher;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\EncryptionException;
use Chronicle\Facades\Chronicle;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('rejects a ciphertext transplanted to another entry (AAD binding)', function () {
    // Two entries for the SAME subject => same DEK, different AAD (different id/action).
    Chronicle::record()->actor(ref('a'))->action('order.a')->subject(ref('s2'))
        ->metadata(['email' => 'a@example.com'])->commit();
    Chronicle::record()->actor(ref('a'))->action('order.b')->subject(ref('s2'))
        ->metadata(['email' => 'b@example.com'])->commit();

    $entryA = Entry::query()->where('action', 'order.a')->firstOrFail();
    $entryB = Entry::query()->where('action', 'order.b')->firstOrFail();

    $dek = app(SubjectKeyManager::class)->stateFor('stdClass', 's2')->dek;
    expect($dek)->not->toBeNull();

    // Take entry A's envelope but try to open it under entry B's AAD.
    $envelopeA = CipherEnvelope::fromArray($entryA->metadata);
    $aadB = PayloadCipher::aad($entryB->id, 'stdClass', 's2', $entryB->action);

    $cipher = app(PayloadCipher::class);

    expect(fn () => $cipher->decrypt($envelopeA, $dek, $aadB))
        ->toThrow(EncryptionException::class);
});
