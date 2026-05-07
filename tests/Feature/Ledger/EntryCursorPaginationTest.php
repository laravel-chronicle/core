<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('cursor paginates ledger entries', function () {
    for ($i = 0; $i < 5; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('cursor.test')
            ->subject(ref('ledger'))
            ->commit();
    }

    $page = Entry::cursorPaginateLedger(2);

    expect($page->items())->toHaveCount(2);
});

it('cursor paginates entries in reverse order', function () {
    Chronicle::record()
        ->actor('system')
        ->action('cursor.first')
        ->subject(ref('ledger'))
        ->commit();

    sleep(1);

    Chronicle::record()
        ->actor('system')
        ->action('cursor.second')
        ->subject(ref('ledger'))
        ->commit();

    $page = Entry::cursorPaginateLatest(1);

    expect($page->first()->action)->toBe('cursor.second');
});
