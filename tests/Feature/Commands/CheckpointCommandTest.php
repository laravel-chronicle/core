<?php

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;

it('creates a checkpoint', function () {
    Chronicle::record()
        ->actor('system')
        ->action('checkpoint.test')
        ->subject('ledger')
        ->commit();

    $this->artisan('chronicle:checkpoint')
        ->assertSuccessful();

    expect(Checkpoint::count())->toBe(1);
});
