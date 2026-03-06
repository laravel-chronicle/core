<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Illuminate\Support\Facades\Artisan;

it('verifies ledger deterministically when entries share created_at timestamps', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.order.one')
        ->subject('ledger')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('verify.order.two')
        ->subject('ledger')
        ->commit();

    $sameTimestamp = now()->startOfSecond();

    Entry::query()->update(['created_at' => $sameTimestamp]);

    Artisan::call('chronicle:verify');

    expect(Artisan::output())
        ->toContain('Chronicle entries verified successfully');
});
