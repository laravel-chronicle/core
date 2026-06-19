<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;

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
