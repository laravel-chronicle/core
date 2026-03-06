<?php

use Chronicle\Entry\PendingEntry;
use Illuminate\Support\Carbon;

function makePendingAttributes(array $overrides = []): array
{
    return array_merge([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'test.event',
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => ['k' => 'v'],
        'context' => ['transport' => 'cli'],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ], $overrides);
}

it('exposes attributes as provided', function () {
    $attributes = makePendingAttributes();
    $entry = new PendingEntry($attributes);

    expect($entry->attributes())->toBe($attributes)
        ->and($entry->payload())->toBe([]);
});

it('stores and exposes payload data', function () {
    $entry = new PendingEntry(makePendingAttributes());
    $payload = ['a' => 1, 'b' => ['x' => true]];

    $entry->setPayload($payload);

    expect($entry->payload())->toBe($payload);
});

it('exposes hashes and checkpoint id', function () {
    $entry = new PendingEntry(makePendingAttributes());

    $entry->setPayloadHash('payload-hash');
    $entry->setChainHash('chain-hash');
    $entry->setCheckpointId('checkpoint-1');

    expect($entry->payloadHash())->toBe('payload-hash')
        ->and($entry->chainHash())->toBe('chain-hash');
});

it('produces database payload with canonical payload merged in', function () {
    $attributes = makePendingAttributes();
    $entry = new PendingEntry($attributes);
    $entry->setPayload(['sorted' => ['a' => 1]]);

    $dbPayload = $entry->toDatabasePayload();

    expect($dbPayload['id'])->toBe($attributes['id'])
        ->and($dbPayload['action'])->toBe($attributes['action'])
        ->and($dbPayload['payload'])->toBe(['sorted' => ['a' => 1]]);
});
