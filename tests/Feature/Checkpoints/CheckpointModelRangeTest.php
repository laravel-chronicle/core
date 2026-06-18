<?php

declare(strict_types=1);

use Chronicle\Anchoring\CheckpointAnchor;
use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Support\Str;

beforeEach(fn () => $this->useEloquentDriver());

it('casts the new range columns and exposes relations', function () {
    $previous = Checkpoint::create([
        'id' => (string) Str::ulid(),
        'chain_hash' => str_repeat('a', 64),
        'signature' => 'sig-1',
        'algorithm' => 'Ed25519',
        'key_id' => 'test',
        'head_id' => (string) Str::ulid(),
        'entry_count' => 3,
        'previous_checkpoint_id' => null,
        'created_at' => now()->subMinute(),
    ]);

    $current = Checkpoint::create([
        'id' => (string) Str::ulid(),
        'chain_hash' => str_repeat('b', 64),
        'signature' => 'sig-2',
        'algorithm' => 'Ed25519',
        'key_id' => 'test',
        'head_id' => (string) Str::ulid(),
        'entry_count' => 6,
        'previous_checkpoint_id' => $previous->id,
        'created_at' => now(),
    ]);

    CheckpointAnchor::create([
        'id' => (string) Str::ulid(),
        'checkpoint_id' => $current->id,
        'provider' => 'null',
        'status' => 'anchored',
        'created_at' => now(),
    ]);

    $current->refresh();

    expect($current->entry_count)->toBe(6)
        ->and($current->previousCheckpoint)->not->toBeNull()
        ->and($current->previousCheckpoint->id)->toBe($previous->id)
        ->and($current->anchors)->toHaveCount(1)
        ->and($current->anchors->first()->provider)->toBe('null');
});
