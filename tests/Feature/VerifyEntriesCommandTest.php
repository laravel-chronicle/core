<?php

use Chronicle\Facades\Chronicle;

it('verifies ledger successfully', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
        ->commit();

    Artisan::call('chronicle:verify');

    expect(Artisan::output())
        ->toContain('Chronicle entries verified successfully');
});
