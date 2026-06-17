<?php

use Chronicle\Encryption\CipherEnvelope;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\EntryVerifier;
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

it('encrypts configured fields in both the payload and the columns', function () {
    Chronicle::record()
        ->actor(ref('a'))
        ->action('order.placed')
        ->subject(ref('s'))
        ->metadata(['email' => 'client@example.com'])
        ->context(['ip' => '10.0.0.1'])
        ->commit();

    $entry = Entry::query()->firstOrFail();

    expect(CipherEnvelope::isEnvelope($entry->metadata))->toBeTrue()
        ->and(json_encode($entry->metadata))->not->toContain('client@example.com')
        ->and($entry->payload['metadata'])->toEqual($entry->metadata)
        ->and($entry->payload['context'])->toEqual($entry->context);
});

it('encrypted entry passes payload-hash, ColumnPayloadDivergence and chain checks', function () {
    Chronicle::record()
        ->actor(ref('a'))->action('order.placed')->subject(ref('s'))
        ->metadata(['email' => 'client@example.com'])
        ->commit();

    $entry = Entry::query()->firstOrFail();

    expect(app(EntryVerifier::class)->verify($entry->id)->isValid())->toBeTrue()
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('skips empty/null fields so they remain cleartext', function () {
    Chronicle::record()
        ->actor(ref('a'))->action('order.placed')->subject(ref('s'))
        ->metadata(['email' => 'client@example.com'])
        ->commit(); // no context, no diff

    $entry = Entry::query()->firstOrFail();

    expect(CipherEnvelope::isEnvelope($entry->metadata))->toBeTrue()
        ->and(CipherEnvelope::isEnvelope($entry->context ?? []))->toBeFalse()
        ->and($entry->diff)->toBeNull();
});

it('is a no-op when encryption is disabled (identical to 1.11)', function () {
    config(['chronicle.encryption.enabled' => false]);

    Chronicle::record()
        ->actor(ref('a'))->action('order.placed')->subject(ref('s'))
        ->metadata(['email' => 'client@example.com'])
        ->commit();

    $entry = Entry::query()->firstOrFail();

    expect($entry->metadata)->toBe(['email' => 'client@example.com'])
        ->and(CipherEnvelope::isEnvelope($entry->metadata))->toBeFalse()
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});
