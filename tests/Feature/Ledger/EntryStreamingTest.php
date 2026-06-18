<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('streams entries in ledger order', function () {
    for ($i = 0; $i < 5; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('stream.test')
            ->subject(ref('ledger'))
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
        ->action('stream.first')
        ->subject(ref('ledger'))
        ->commit();

    sleep(1);

    Chronicle::record()
        ->actor('system')
        ->action('stream.second')
        ->subject(ref('ledger'))
        ->commit();

    $actions = Entry::streamLatest()
        ->take(2)
        ->pluck('action')
        ->values();

    expect($actions->first())->toBe('stream.second');
});
