<?php

use Chronicle\Encryption\SubjectKey;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('leaves no DEK and no plaintext after erasure; decryption is impossible', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    app('chronicle')->eraseSubject('stdClass', 's2', 'dpo@acme.test', 'gdpr');

    // 1. No wrapped DEK survives in the DB; the row is tombstoned.
    $key = SubjectKey::query()->where('subject_id', 's2')->firstOrFail();
    expect($key->wrapped_dek)->toBeNull()
        ->and($key->status)->toBe('erased');

    // 2. The read-side DEK state is erased (no plaintext DEK reachable).
    $state = app(SubjectKeyManager::class)->stateFor('stdClass', 's2');
    expect($state->dek)->toBeNull()
        ->and($state->erased)->toBeTrue();

    // 3. The stored ciphertext column never contained the plaintext.
    $entry = Entry::query()->where('action', 'order.placed')->firstOrFail();
    expect(json_encode($entry->getAttributes()))->not->toContain('client@example.com')
        // 4. Decryption is impossible - the read path returns a tombstone, not data.
        ->and($entry->decryptedMetadata())->toHaveKey('_erased');

});
