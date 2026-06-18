<?php

declare(strict_types=1);

use Chronicle\Storage\SerializesEntryAttributes;
use Illuminate\Support\Carbon;

it('serializes all entry fields with json-encoded array columns', function () {
    $driver = new class
    {
        use SerializesEntryAttributes;

        /** @param array<string, mixed> $entry
         *  @return array<string, mixed> */
        public function expose(array $entry): array
        {
            return $this->toEntryAttributes($entry);
        }
    };

    $now = Carbon::parse('2026-01-01 00:00:00', 'UTC');

    $entry = [
        'id' => '01JMQP5M2M0P0X2A9BTD3M7D01',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'payload' => ['action' => 'order.placed'],
        'payload_hash' => 'abc123',
        'chain_hash' => 'def456',
        'metadata' => ['note' => 'test'],
        'context' => ['ip' => '127.0.0.1'],
        'tags' => ['billing'],
        'diff' => ['amount' => ['old' => 100, 'new' => 200]],
        'correlation_id' => 'corr-1',
        'checkpoint_id' => null,
        'created_at' => $now,
    ];

    $attrs = $driver->expose($entry);

    expect($attrs['id'])->toBe('01JMQP5M2M0P0X2A9BTD3M7D01')
        ->and($attrs['actor_type'])->toBe('App\\Models\\User')
        ->and($attrs['payload'])->toBe('{"action":"order.placed"}')
        ->and($attrs['metadata'])->toBe('{"note":"test"}')
        ->and($attrs['context'])->toBe('{"ip":"127.0.0.1"}')
        ->and($attrs['tags'])->toBe('["billing"]')
        ->and($attrs['diff'])->toBe('{"amount":{"old":100,"new":200}}')
        ->and($attrs['payload_hash'])->toBe('abc123')
        ->and($attrs['chain_hash'])->toBe('def456')
        ->and($attrs['correlation_id'])->toBe('corr-1')
        ->and($attrs['checkpoint_id'])->toBeNull()
        ->and($attrs['created_at'])->toBe($now);
});

it('stores null diff as SQL NULL, not the JSON string "null"', function () {
    $driver = new class
    {
        use SerializesEntryAttributes;

        /** @param array<string, mixed> $entry
         *  @return array<string, mixed> */
        public function expose(array $entry): array
        {
            return $this->toEntryAttributes($entry);
        }
    };

    $entry = [
        'id' => '01JMQP5M2M0P0X2A9BTD3M7D02',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '1',
        'action' => 'order.created',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '1',
        'payload' => [],
        'payload_hash' => 'abc',
        'chain_hash' => 'def',
        'metadata' => [],
        'context' => [],
        'tags' => [],
        'diff' => null,
        'correlation_id' => null,
        'checkpoint_id' => null,
        'created_at' => Carbon::parse('2026-01-01'),
    ];

    $attrs = $driver->expose($entry);

    expect($attrs['diff'])->toBeNull('diff should be SQL NULL, not the string "null"');
});
