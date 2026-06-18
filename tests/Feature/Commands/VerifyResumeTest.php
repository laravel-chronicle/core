<?php

declare(strict_types=1);

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\VerificationRun;

beforeEach(fn () => $this->useEloquentDriver());

it('records a run after a full verify and resume checks only the appended tail', function () {
    foreach (range(1, 2) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    app(CheckpointCreator::class)->create();

    // Full verify records a run anchored at the latest checkpoint.
    $this->artisan('chronicle:verify')->assertSuccessful();
    expect(VerificationRun::query()->count())->toBe(1);

    // Append a new segment + checkpoint.
    foreach (range(3, 5) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    app(CheckpointCreator::class)->create();

    // Resume verifies only the 3 appended entries.
    $this->artisan('chronicle:verify', ['--resume' => true])
        ->expectsOutputToContain('Records checked: 3')
        ->assertSuccessful();
});

it('resume falls back to full verify when no prior run exists', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    app(CheckpointCreator::class)->create();

    $this->artisan('chronicle:verify', ['--resume' => true])
        ->expectsOutputToContain('no previous run')
        ->assertSuccessful();
});
