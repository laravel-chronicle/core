<?php

declare(strict_types=1);

use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.encryption.enabled' => true,
        'chronicle.encryption.fields' => ['metadata', 'context', 'diff'],
        'chronicle.encryption.kek.key' => base64_encode(random_bytes(32)),
        'chronicle.encryption.kek.id' => 'local',
    ]);
});

function recordEncrypted(): Entry
{
    Chronicle::record()
        ->actor(ref('a'))->action('order.placed')->subject(ref('s'))
        ->metadata(['email' => 'client@example.com'])
        ->commit();

    return Entry::query()->firstOrFail();
}

it('decrypts a configured field on read when the DEK exists', function () {
    $entry = recordEncrypted();

    expect($entry->decryptedMetadata())->toBe(['email' => 'client@example.com'])
        ->and($entry->erased())->toBeFalse();
});

it('returns a tombstone after the subject DEK is destroyed', function () {
    $entry = recordEncrypted();

    app(SubjectKeyManager::class)->destroy('stdClass', 's'); // subject_type/id as resolved

    $fresh = Entry::query()->firstOrFail();

    expect($fresh->erased())->toBeTrue();
    $value = $fresh->decryptedMetadata();
    expect($value)->toHaveKey('_erased')
        ->and($value['_erased'])->toBeTrue()
        ->and($value)->toHaveKey('erased_at');
});

it('still lists an erased entry by its cleartext envelope', function () {
    $entry = recordEncrypted();
    app(SubjectKeyManager::class)->destroy('stdClass', 's');

    // Cleartext columns are never encrypted, so query filters still work.
    $found = Entry::query()->where('action', 'order.placed')->get();

    expect($found)->toHaveCount(1)
        ->and($found->first()->erased())->toBeTrue();
});

it('leaves cleartext fields untouched on read', function () {
    config(['chronicle.encryption.enabled' => false]);
    Chronicle::record()
        ->actor(ref('a'))->action('order.placed')->subject(ref('s'))
        ->metadata(['email' => 'plain@example.com'])
        ->commit();

    $entry = Entry::query()->firstOrFail();

    expect($entry->decryptedMetadata())->toBe(['email' => 'plain@example.com']);
});
