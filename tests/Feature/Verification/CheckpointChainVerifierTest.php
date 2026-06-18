<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\CheckpointChainVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

function recordAndCheckpoint(int $count): void
{
    foreach (range(1, $count) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    app(CheckpointCreator::class)->create();
}

it('verifies a clean checkpoint chain', function () {
    recordAndCheckpoint(2);
    recordAndCheckpoint(2);
    recordAndCheckpoint(2);

    $result = app(CheckpointChainVerifier::class)->verify();

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(3);
});

it('detects a tampered checkpoint signature', function () {
    recordAndCheckpoint(2);
    $cp = Checkpoint::query()->firstOrFail();

    DB::table('chronicle_checkpoints')->where('id', $cp->id)
        ->update(['signature' => base64_encode(str_repeat('x', 64))]);

    $result = app(CheckpointChainVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointSignatureInvalid->value);
});

it('detects a checkpoint whose chain_hash disagrees with its head entry', function () {
    recordAndCheckpoint(2);
    $cp = Checkpoint::query()->firstOrFail();

    // Move the head pointer to a different entry so chain_hash != head entry chain_hash.
    $other = Entry::query()->where('sequence', 1)->firstOrFail();
    DB::table('chronicle_checkpoints')->where('id', $cp->id)->update(['head_id' => $other->id]);

    $result = app(CheckpointChainVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointHeadMismatch->value);
});

it('detects broken previous-checkpoint linkage', function () {
    recordAndCheckpoint(2);
    recordAndCheckpoint(2);

    $second = Checkpoint::query()->orderByDesc('entry_count')->firstOrFail();
    DB::table('chronicle_checkpoints')->where('id', $second->id)
        ->update(['previous_checkpoint_id' => null]);

    $result = app(CheckpointChainVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointChainBroken->value);
});
