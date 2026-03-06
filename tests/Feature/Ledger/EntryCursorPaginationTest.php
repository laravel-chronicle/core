<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('cursor paginates ledger entries', function () {
    for ($i = 0; $i < 5; $i++) {
        Chronicle::record()
            ->actor('system')
            ->action('test')
            ->subject('ledger')
            ->commit();
    }

    $page = Entry::cursorPaginateLedger(2);

    expect($page->items())->toHaveCount(2);
});

it('cursor paginates entries in reverse order', function () {
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

    $page = Entry::cursorPaginateLatest(1);

    expect($page->first()->action)->toBe('second');
});
