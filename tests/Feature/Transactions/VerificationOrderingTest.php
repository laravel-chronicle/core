<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Artisan;

it('verifies ledger deterministically when entries share created_at timestamps', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.one')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('verify.two')
        ->subject('ledger')
        ->commit();

    $sameTimestamp = now()->startOfSecond();

    Entry::query()->update(['created_at' => $sameTimestamp]);

    Artisan::call('chronicle:verify');

    expect(Artisan::output())
        ->toContain('Chain integrity verified');
});
