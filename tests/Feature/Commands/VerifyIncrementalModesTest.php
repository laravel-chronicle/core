<?php

declare(strict_types=1);

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

/** Build N segments of 2 entries, each capped by a checkpoint. Returns checkpoints oldest-first. */
function buildSegmentedLedger(int $segments): array
{
    $checkpoints = [];
    foreach (range(1, $segments) as $s) {
        Chronicle::record()->actor(ref('a'))->action("s$s.one")->subject(ref('x'))->commit();
        Chronicle::record()->actor(ref('a'))->action("s$s.two")->subject(ref('x'))->commit();
        $checkpoints[] = app(CheckpointCreator::class)->create();
    }

    return $checkpoints;
}

it('checkpoints-only agrees with full verify on a clean ledger', function () {
    buildSegmentedLedger(3);

    $this->artisan('chronicle:verify')->assertSuccessful();
    $this->artisan('chronicle:verify', ['--checkpoints-only' => true])->assertSuccessful();
});

it('checkpoints-only fails when a checkpoint is tampered', function () {
    $cps = buildSegmentedLedger(3);
    DB::table('chronicle_checkpoints')->where('id', $cps[1]->id)
        ->update(['signature' => base64_encode(str_repeat('x', 64))]);

    $this->artisan('chronicle:verify', ['--checkpoints-only' => true])->assertFailed();
    // Full verify agrees: covered entries carry checkpoint_id (populated at
    // creation since v1.11), so the per-entry checkpoint branch verifies the
    // tampered signature and fails too.
    $this->artisan('chronicle:verify')->assertFailed();
});

it('a scoped --from/--to segment catches a tamper inside it and full verify agrees', function () {
    $cps = buildSegmentedLedger(3);

    // Tamper an entry in segment 2 (between checkpoint 1 and checkpoint 2).
    $seg2entry = Entry::query()->orderBy('sequence')->skip(2)->first(); // sequence 3
    DB::table('chronicle_entries')->where('id', $seg2entry->id)
        ->update(['payload_hash' => str_repeat('0', 64)]);

    // Segment covering it fails...
    $this->artisan('chronicle:verify', [
        '--from-checkpoint' => $cps[0]->id,
        '--to-checkpoint' => $cps[1]->id,
    ])->assertFailed();

    // ...a segment NOT covering it passes...
    $this->artisan('chronicle:verify', [
        '--from-checkpoint' => $cps[1]->id,
        '--to-checkpoint' => $cps[2]->id,
    ])->assertSuccessful();

    // ...and full verify agrees something is wrong.
    $this->artisan('chronicle:verify')->assertFailed();
});

it('since-last-checkpoint verifies only the tail and agrees with full verify', function () {
    buildSegmentedLedger(2);
    // Append an unanchored tail entry.
    Chronicle::record()->actor(ref('a'))->action('tail.one')->subject(ref('x'))->commit();

    $this->artisan('chronicle:verify', ['--since-last-checkpoint' => true])->assertSuccessful();
    $this->artisan('chronicle:verify')->assertSuccessful();
});

it('falls back to full verify with a warning when checkpoints are not backfilled', function () {
    buildSegmentedLedger(2);
    // Emulate a pre-1.11 ledger: strip head_id from all checkpoints.
    DB::table('chronicle_checkpoints')->update(['head_id' => null]);

    $this->artisan('chronicle:verify', ['--checkpoints-only' => true])
        ->expectsOutputToContain('not backfilled')
        ->assertSuccessful();
});

it('keeps single-entry and default full verify behavior', function () {
    buildSegmentedLedger(1);
    $entry = Entry::query()->orderBy('sequence')->firstOrFail();

    $this->artisan('chronicle:verify', ['--entry' => $entry->id])->assertSuccessful();
    $this->artisan('chronicle:verify')->assertSuccessful();
});
