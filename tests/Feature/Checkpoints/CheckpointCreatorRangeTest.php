<?php

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

it('records head, count, and linkage and stamps checkpoint_id on covered entries', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }

    $head = Entry::query()->orderByDesc('sequence')->first();
    $checkpoint = app(CheckpointCreator::class)->create();

    expect($checkpoint->head_id)->toBe($head->id)
        ->and($checkpoint->entry_count)->toBe(3)
        ->and($checkpoint->previous_checkpoint_id)->toBeNull()
        ->and($checkpoint->entries()->count())->toBe(3)
        ->and(Entry::query()->whereNull('checkpoint_id')->count())->toBe(0);
});

it('links a second checkpoint to the first and only covers new entries', function () {
    foreach (range(1, 2) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    $first = app(CheckpointCreator::class)->create();

    foreach (range(3, 5) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    $second = app(CheckpointCreator::class)->create();

    expect($second->previous_checkpoint_id)->toBe($first->id)
        ->and($second->entry_count)->toBe(5)
        ->and($first->entries()->count())->toBe(2)
        ->and($second->entries()->count())->toBe(3);
});

it('makes the IntegrityVerifier checkpoint branch execute', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    // Branch only runs because checkpoint_id is now populated. Tampering the
    // checkpoint signature must therefore be detected by full verification.
    expect(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();

    DB::table('chronicle_checkpoints')->where('id', $checkpoint->id)
        ->update(['signature' => 'tampered']);

    $result = app(IntegrityVerifier::class)->verify();

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointSignatureInvalid->value);
});

it('does not change payload_hash or chain_hash when a checkpoint is created', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }

    $before = Entry::query()->orderBy('sequence')
        ->get(['id', 'payload_hash', 'chain_hash'])
        ->mapWithKeys(fn ($e) => [$e->id => [$e->payload_hash, $e->chain_hash]])
        ->all();

    app(CheckpointCreator::class)->create();

    $after = Entry::query()->orderBy('sequence')
        ->get(['id', 'payload_hash', 'chain_hash'])
        ->mapWithKeys(fn ($e) => [$e->id => [$e->payload_hash, $e->chain_hash]])
        ->all();

    expect($after)->toBe($before);
});

it('still dedups when the chain head is unchanged', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();

    $first = app(CheckpointCreator::class)->create();
    $second = app(CheckpointCreator::class)->create();

    expect($second->id)->toBe($first->id);
});

it('still throws on an empty ledger', function () {
    expect(fn () => app(CheckpointCreator::class)->create())
        ->toThrow(RuntimeException::class);
});
