<?php

use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Anchoring\NullAnchor;
use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => ['null' => ['provider' => NullAnchor::class]],
    ]);
});

it('anchors synchronously with chronicle:checkpoint --anchor', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();

    $this->artisan('chronicle:checkpoint', ['--anchor' => true])->assertSuccessful();

    $checkpoint = Checkpoint::query()->firstOrFail();
    expect($checkpoint->anchors()->where('status', 'anchored')->count())->toBe(1);
});

it('retries failed anchors with chronicle:anchor:retry', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    // Seed a failed row.
    DB::table('chronicle_checkpoint_anchors')->insert([
        'id' => (string) Str::ulid(),
        'checkpoint_id' => $checkpoint->id,
        'provider' => 'null',
        'status' => 'failed',
        'created_at' => now(),
    ]);

    $this->artisan('chronicle:anchor:retry', ['--status' => 'failed'])->assertSuccessful();

    expect($checkpoint->anchors()->where('provider', 'null')->firstOrFail()->status)->toBe('anchored');
});

it('verifies stored anchors with chronicle:anchor:verify', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();
    app(CheckpointAnchorer::class)->anchor($checkpoint, 'null');

    $this->artisan('chronicle:anchor:verify', ['--checkpoint' => $checkpoint->id])->assertSuccessful();

    // Corrupt the proof -> verify fails.
    DB::table('chronicle_checkpoint_anchors')->where('checkpoint_id', $checkpoint->id)
        ->update(['proof' => str_repeat('0', 64)]);

    $this->artisan('chronicle:anchor:verify', ['--checkpoint' => $checkpoint->id])->assertFailed();
});
