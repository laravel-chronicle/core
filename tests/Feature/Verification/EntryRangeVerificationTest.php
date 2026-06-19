<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;

beforeEach(fn () => $this->useEloquentDriver());

/**
 * Record 2 entries per segment, capping each with a checkpoint.
 * For N segments: entries 1..2N; checkpoint head sequences are 2,4,...,2N.
 *
 * @return array<int, Checkpoint> oldest-first
 *
 * @throws Throwable
 */
function rangeSeedSegments(int $segments): array
{
    $checkpoints = [];
    foreach (range(1, $segments) as $s) {
        Chronicle::record()->actor(ref('a'))->action("s$s.one")->subject(ref('x'))->commit();
        Chronicle::record()->actor(ref('a'))->action("s$s.two")->subject(ref('x'))->commit();
        $checkpoints[] = app(CheckpointCreator::class)->create();
    }

    return $checkpoints;
}

it('verifies a clean range within a single checkpoint segment', function () {
    rangeSeedSegments(3); // heads 2,4,6

    // [3,4] is the segment between checkpoint c0 (head 2) and c1 (head 4).
    $result = app(IntegrityVerifier::class)->verifyEntryRange(3, 4);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(2);
});

it('verifies a clean range spanning several checkpoints', function () {
    rangeSeedSegments(3); // heads 2,4,6

    // [3,6] spans c1 and c2; start anchor is c0 (head 2), end anchor is c2 (head 6).
    $result = app(IntegrityVerifier::class)->verifyEntryRange(3, 6);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(4);
});

it('rejects an invalid range argument', function () {
    rangeSeedSegments(1);

    app(IntegrityVerifier::class)->verifyEntryRange(4, 2);
})->throws(InvalidArgumentException::class);

it('verifies a range starting at genesis (before the first checkpoint)', function () {
    rangeSeedSegments(3); // heads 2,4,6

    // [1,4]: no checkpoint head < 1, so previousChain is GENESIS; end anchor c1 (head 4).
    $result = app(IntegrityVerifier::class)->verifyEntryRange(1, 4);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(4);
});

it('verifies a tail range past the last checkpoint by recomputing to the head', function () {
    rangeSeedSegments(2); // heads 2,4
    // Two unanchored entries after the last checkpoint (sequences 5, 6).
    Chronicle::record()->actor(ref('a'))->action('tail.one')->subject(ref('x'))->commit();
    Chronicle::record()->actor(ref('a'))->action('tail.two')->subject(ref('x'))->commit();

    // [5,6] has no trailing checkpoint: falls back to verifyFrom(c1 head 4),
    // which recomputes entries 5 and 6 to the head.
    $result = app(IntegrityVerifier::class)->verifyEntryRange(5, 6);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(2);
});

it('verifies a range when no checkpoints exist (genesis to head)', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("n.$i")->subject(ref('x'))->commit();
    }

    // No checkpoints: start is genesis, end is unanchored tail => full verify().
    $result = app(IntegrityVerifier::class)->verifyEntryRange(1, 3);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(3);
});

it('detects tampering with a row inside the requested range', function () {
    rangeSeedSegments(3); // entries 1..6, heads 2,4,6

    // Tamper entry at sequence 4, which is inside the requested range [3,5].
    $target = Entry::query()->where('sequence', 4)->firstOrFail();
    DB::table('chronicle_entries')->where('id', $target->id)
        ->update(['payload_hash' => str_repeat('0', 64)]);

    $result = app(IntegrityVerifier::class)->verifyEntryRange(3, 5);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::PayloadHashMismatch->value)
        ->and($result->entryId())->toBe($target->id);
});

it('fails when the enclosing start checkpoint signature is invalid', function () {
    [$c0, $c1, $c2] = rangeSeedSegments(3); // heads 2,4,6

    // For range [3,4] the start anchor is c0 (head 2). Corrupt its signature.
    DB::table('chronicle_checkpoints')->where('id', $c0->id)
        ->update(['signature' => base64_encode(str_repeat('x', 64))]);

    $result = app(IntegrityVerifier::class)->verifyEntryRange(3, 4);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointSignatureInvalid->value)
        ->and($result->entryId())->toBe($c0->id);
});

it('fails when the enclosing end checkpoint signature is invalid', function () {
    [$c0, $c1, $c2] = rangeSeedSegments(3); // heads 2,4,6

    // For range [1,4] the start is genesis and the end anchor is c1 (head 4).
    DB::table('chronicle_checkpoints')->where('id', $c1->id)
        ->update(['signature' => base64_encode(str_repeat('x', 64))]);

    $result = app(IntegrityVerifier::class)->verifyEntryRange(1, 4);

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::CheckpointSignatureInvalid->value)
        ->and($result->entryId())->toBe($c1->id);
});
