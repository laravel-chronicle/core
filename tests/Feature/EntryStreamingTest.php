<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('streams entries in ledger order', function () {
    for ($i = 0; $i < 5; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('stream.test')
            ->subject('ledger')
            ->commit();
    }

    $count = 0;

    Entry::stream()->each(function () use (&$count) {
        $count++;
    });

    expect($count)->toBe(5);
});

it('streams entries in reverse order', function () {
    Chronicle::record()
        ->actor('system')
        ->action('first')
        ->subject('ledger')
        ->commit();

    sleep(1);

    Chronicle::record()
        ->actor('system')
        ->action('second')
        ->subject('ledger')
        ->commit();

    $actions = Entry::streamLatest()
        ->take(2)
        ->pluck('action')
        ->values();

    expect($actions->first())->toBe('second');
});
