<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\PendingEntry;
use Chronicle\Facades\Chronicle;
use Chronicle\Tests\Feature\UI\UiTestCase;
use Chronicle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

pest()->extends(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__);

uses(UiTestCase::class)->in(__DIR__.'/Feature/UI');

/**
 * Create a plain object reference for use as an actor / subject in tests.
 * DefaultReferenceResolver resolves any object with a public $id property.
 */
function ref(string $id): object
{
    $obj = new stdClass;
    $obj->id = $id;

    return $obj;
}

/**
 * Record one entry and create a checkpoint anchoring it. Used by anchoring tests.
 * The caller must enable the eloquent driver first (e.g. $this->useEloquentDriver()).
 */
function makeAnchorCheckpoint(): Checkpoint
{
    Chronicle::record()->actor(ref('a'))->action('anchor.one')->subject(ref('s'))->commit();

    return app(CheckpointCreator::class)->create();
}

function makePolicyPending(): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

function validEntryPayload(): array
{
    return [
        'id' => Str::ulid()->toString(),
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '1',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '42',
        'payload' => ['action' => 'order.placed'],
        'payload_hash' => hash('sha256', '{}'),
        'chain_hash' => null,
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'checkpoint_id' => null,
        'created_at' => Carbon::now(),
    ];
}

function seedUiEntries(int $count = 5): void
{
    for ($i = 1; $i <= $count; $i++) {
        DB::table(
            config('chronicle.tables.entries', 'chronicle_entries')
        )->insert([
            'id' => Str::ulid()->toString(),
            'actor_type' => 'App\\Models\\User',
            'actor_id' => (string) $i,
            'action' => $i % 2 === 0 ? 'invoice.sent' : 'invoice.created',
            'subject_type' => 'App\\Models\\Invoice',
            'subject_id' => (string) $i,
            'payload' => json_encode(['action' => 'invoice.created'], JSON_THROW_ON_ERROR),
            'payload_hash' => hash('sha256', '{}'),
            'chain_hash' => hash('sha256', (string) $i),
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'context' => json_encode([], JSON_THROW_ON_ERROR),
            'tags' => json_encode($i % 3 === 0 ? ['billing'] : [], JSON_THROW_ON_ERROR),
            'diff' => json_encode(null, JSON_THROW_ON_ERROR),
            'correlation_id' => null,
            'checkpoint_id' => null,
            'sequence' => $i,
            'created_at' => now()->subMinutes($i)->toDateTimeString(),
        ]);
    }
}
