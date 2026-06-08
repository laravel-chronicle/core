<?php

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

/**
 * Build a 1.10-shaped checkpoint: signed head chain_hash, but no range columns
 * and no checkpoint_id stamped on entries (simulating the pre-1.11 gap).
 */
function seedLegacyCheckpoint(): Checkpoint
{
    // Create a 1.11 checkpoint, then strip the range data + coverage to emulate
    // a pre-1.11 (1.10-shaped) row.
    $checkpoint = app(CheckpointCreator::class)->create();

    DB::table('chronicle_checkpoints')->where('id', $checkpoint->id)->update([
        'head_id' => null,
        'entry_count' => null,
        'previous_checkpoint_id' => null,
    ]);
    DB::table('chronicle_entries')->update(['checkpoint_id' => null]);

    return Checkpoint::findOrFail($checkpoint->id);
}

it('backfills range columns and checkpoint_id for a legacy checkpoint', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    $legacy = seedLegacyCheckpoint();
    $head = Entry::query()->orderByDesc('sequence')->firstOrFail();

    $this->artisan('chronicle:checkpoints:backfill')->assertSuccessful();

    $legacy->refresh();

    expect($legacy->head_id)->toBe($head->id)
        ->and($legacy->entry_count)->toBe(3)
        ->and($legacy->previous_checkpoint_id)->toBeNull()
        ->and(Entry::query()->whereNull('checkpoint_id')->count())->toBe(0);
});

it('is idempotent on re-run', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    seedLegacyCheckpoint();

    $this->artisan('chronicle:checkpoints:backfill')->assertSuccessful();
    $first = Checkpoint::query()->firstOrFail()->only(['head_id', 'entry_count']);

    $this->artisan('chronicle:checkpoints:backfill')->assertSuccessful();
    $second = Checkpoint::query()->firstOrFail()->only(['head_id', 'entry_count']);

    expect($second)->toBe($first);
});

it('writes nothing in dry-run mode', function () {
    foreach (range(1, 3) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    $legacy = seedLegacyCheckpoint();

    $this->artisan('chronicle:checkpoints:backfill', ['--dry-run' => true])
        ->assertSuccessful();

    $legacy->refresh();

    expect($legacy->head_id)->toBeNull()
        ->and(Entry::query()->whereNull('checkpoint_id')->count())->toBe(3);
});
