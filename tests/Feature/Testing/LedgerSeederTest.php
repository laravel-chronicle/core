<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;
use Chronicle\Testing\LedgerSeeder;
use Chronicle\Verification\CheckpointChainVerifier;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(fn () => $this->useEloquentDriver());

it('seeds a valid, verifiable chain of entries', function () {
    $result = LedgerSeeder::make()->count(50)->seed();

    expect($result->entries)->toBe(50)
        ->and($result->checkpoints)->toBe(0)
        ->and(Chronicle::query()->count())->toBe(50)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('defaults to a system actor and a synthetic subject', function () {
    LedgerSeeder::make()->count(1)->seed();

    $entry = Chronicle::query()->oldest()->first();

    expect($entry->actor_type)->toBe('system')
        ->and($entry->actor_id)->toBe('system')
        ->and($entry->action)->toBe('seed.recorded')
        ->and($entry->subject_id)->toBe('1');
});

it('writes periodic and final checkpoints that verify', function () {
    // 25 entries, checkpoint every 10: boundaries at 10 and 20, plus a final
    // checkpoint covering 21-25 (since 25 % 10 != 0) => 3 checkpoints.
    $result = LedgerSeeder::make()->count(25)->checkpointEvery(10)->seed();

    expect($result->checkpoints)->toBe(3)
        ->and($result->lastCheckpointId)->not->toBeNull()
        ->and(Checkpoint::query()->count())->toBe(3)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue()
        ->and(app(CheckpointChainVerifier::class)->verify()->isValid())->toBeTrue()
        // every entry is anchored by a checkpoint
        ->and(Chronicle::newEntryQuery()->whereNull('checkpoint_id')->count())->toBe(0);
});

it('does not add a redundant final checkpoint when the count aligns', function () {
    // 20 entries, every 10: boundaries at 10 and 20 exactly => 2 checkpoints.
    $result = LedgerSeeder::make()->count(20)->checkpointEvery(10)->seed();

    expect($result->checkpoints)->toBe(2)
        ->and(Checkpoint::query()->count())->toBe(2)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});
