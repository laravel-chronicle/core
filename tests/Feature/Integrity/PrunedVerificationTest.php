<?php

declare(strict_types=1);

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

it('verifies a pruned ledger from a boundary checkpoint', function () {
    // Entries 1..3, then a properly-signed checkpoint anchoring the head (sequence 3).
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }

    $boundaryCheckpoint = app(CheckpointCreator::class)->create();

    // Entries 4..6 recorded after the checkpoint.
    foreach (range(4, 6) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }

    // Prune everything up to and including the checkpoint boundary (sequence <= 3).
    DB::table('chronicle_entries')->where('sequence', '<=', 3)->delete();

    // From-genesis verification can no longer pass - the early history is gone.
    expect(app(IntegrityVerifier::class)->verify()->isValid())->toBeFalse();

    // Seeded verification from the boundary checkpoint passes and checks 4,5,6.
    $result = app(IntegrityVerifier::class)->verifyFrom($boundaryCheckpoint);

    expect($result->isValid())->toBeTrue()
        ->and($result->checked())->toBe(3);
});
