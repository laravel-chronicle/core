<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Checkpoint;

it('creates a checkpoint', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
        ->commit();

    $this->artisan('chronicle:checkpoint')
        ->assertSuccessful();

    expect(Checkpoint::count())->toBe(1);
});
