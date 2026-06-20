<?php

declare(strict_types=1);

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->useEloquentDriver());

function cmdSeedSegments(int $segments): void
{
    foreach (range(1, $segments) as $s) {
        Chronicle::record()->actor(ref('a'))->action("s$s.one")->subject(ref('x'))->commit();
        Chronicle::record()->actor(ref('a'))->action("s$s.two")->subject(ref('x'))->commit();
        app(CheckpointCreator::class)->create();
    }
}

it('passes for a clean entry range', function () {
    cmdSeedSegments(3); // entries 1..6

    $from = Entry::query()->where('sequence', 3)->firstOrFail();
    $to = Entry::query()->where('sequence', 6)->firstOrFail();

    $this->artisan('chronicle:verify', ['--from' => $from->id, '--to' => $to->id])
        ->assertSuccessful();
});

it('fails for a range containing a tampered row', function () {
    cmdSeedSegments(3);

    $target = Entry::query()->where('sequence', 4)->firstOrFail();
    DB::table('chronicle_entries')->where('id', $target->id)
        ->update(['payload_hash' => str_repeat('0', 64)]);

    $from = Entry::query()->where('sequence', 3)->firstOrFail();
    $to = Entry::query()->where('sequence', 5)->firstOrFail();

    $this->artisan('chronicle:verify', ['--from' => $from->id, '--to' => $to->id])
        ->assertFailed();
});

it('errors when a range endpoint entry does not exist', function () {
    cmdSeedSegments(1);

    $this->artisan('chronicle:verify', ['--from' => 'nope', '--to' => 'nope'])
        ->assertFailed();
});
