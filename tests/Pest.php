<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

pest()->extends(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__);

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
