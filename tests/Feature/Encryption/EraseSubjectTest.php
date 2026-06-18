<?php

use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.fields' => ['metadata', 'context', 'diff'],
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

it('erases a subject: shreds the DEK, reads tombstone, ledger still verifies', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    $entry = Entry::query()->where('action', 'order.placed')->firstOrFail();

    $result = app('chronicle')->eraseSubject($entry->subject_type, (string) $entry->subject_id, 'dpo@acme.test', 'gdpr-request');
    expect($result)->toBeTrue();

    // DEK is gone: original entry now reads a tombstone.
    $fresh = Entry::query()->where('action', 'order.placed')->firstOrFail();
    expect($fresh->decryptedMetadata())->toHaveKey('_erased')
        ->and(app(SubjectKeyManager::class)->stateFor($entry->subject_type, (string) $entry->subject_id)->erased)->toBeTrue();

    // Ledger still verifies after erasure.
    expect(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('records a PII-free subject.erased proof', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    app('chronicle')->eraseSubject('stdClass', 's2', 'dpo@acme.test', 'gdpr-request');

    $proof = Entry::query()->where('action', 'subject.erased')->firstOrFail();

    // Subject ref preserved; proof is readable cleartext; no erased PII anywhere.
    expect($proof->subject_type)->toBe('stdClass')
        ->and($proof->subject_id)->toBe('s2')
        ->and($proof->decryptedMetadata())->toBe(['requester' => 'dpo@acme.test', 'reason' => 'gdpr-request'])
        ->and(json_encode($proof->getAttributes()))->not->toContain('client@example.com');
});

it('is idempotent: a second erase is a no-op and records no second proof', function () {
    Chronicle::record()->actor(ref('a'))->action('order.placed')->subject(ref('s2'))
        ->metadata(['email' => 'client@example.com'])->commit();

    expect(app('chronicle')->eraseSubject('stdClass', 's2'))->toBeTrue()
        ->and(app('chronicle')->eraseSubject('stdClass', 's2'))->toBeFalse()
        ->and(Entry::query()->where('action', 'subject.erased')->count())->toBe(1);
});

it('leaves new entries for an erased subject cleartext instead of throwing', function () {
    app('chronicle')->eraseSubject('stdClass', 's2');

    // Recording for an erased subject must not throw and must stay cleartext.
    Chronicle::record()->actor(ref('a'))->action('post.erase')->subject(ref('s2'))
        ->metadata(['note' => 'no-pii'])->commit();

    $entry = Entry::query()->where('action', 'post.erase')->firstOrFail();
    expect($entry->metadata)->toBe(['note' => 'no-pii']);
});
