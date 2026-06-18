<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

function recordEntries(int $count): void
{
    foreach (range(1, $count) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
}

it('verifies a clean segment seeded from genesis to the head chain hash', function () {
    recordEntries(5);

    $head = Entry::query()->orderByDesc('sequence')->firstOrFail();

    $result = app(IntegrityVerifier::class)->verifySegment(
        previousChain: ChainHasher::GENESIS,
        afterSequence: 0,
        throughSequence: $head->sequence,
        expectedEndingChain: $head->chain_hash,
    );

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(5);
});

it('verifies a bounded interior segment from a trusted prior chain hash', function () {
    recordEntries(6);

    // Trust entry sequence 2; verify the segment (2, 4].
    $start = Entry::query()->where('sequence', 2)->firstOrFail();
    $end = Entry::query()->where('sequence', 4)->firstOrFail();

    $result = app(IntegrityVerifier::class)->verifySegment(
        previousChain: $start->chain_hash,
        afterSequence: 2,
        throughSequence: 4,
        expectedEndingChain: $end->chain_hash,
    );

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(2);
});

it('fails a segment whose entry was tampered', function () {
    recordEntries(5);

    $head = Entry::query()->orderByDesc('sequence')->firstOrFail();
    DB::table('chronicle_entries')->where('sequence', 3)
        ->update(['payload_hash' => str_repeat('0', 64)]);

    $result = app(IntegrityVerifier::class)->verifySegment(
        previousChain: ChainHasher::GENESIS,
        afterSequence: 0,
        throughSequence: $head->sequence,
        expectedEndingChain: $head->chain_hash,
    );

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::PayloadHashMismatch->value);
});

it('flags a discontinuity when the ending chain hash does not match', function () {
    recordEntries(5);

    // Expect a wrong ending chain hash for an otherwise-clean range.
    $result = app(IntegrityVerifier::class)->verifySegment(
        previousChain: ChainHasher::GENESIS,
        afterSequence: 0,
        throughSequence: 4, // stop one short of the real head
        expectedEndingChain: str_repeat('f', 64),
    );

    expect($result->isValid())->toBeFalse()
        ->and($result->failureType())->toBe(VerificationFailure::SegmentDiscontinuous->value);
});
