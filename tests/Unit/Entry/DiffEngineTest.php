<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('records explicit diff', function () {
    Chronicle::record()
        ->actor('system')
        ->action('diff.recorded')
        ->subject(ref('ledger'))
        ->diff([
            'amount' => [
                'old' => 10,
                'new' => 20,
            ],
        ])
        ->commit();

    $entry = Entry::first();

    expect($entry->diff['amount']['old'])->toBe(10);
});

it('supports change helper', function () {
    Chronicle::record()
        ->actor('system')
        ->action('diff.changed')
        ->subject(ref('ledger'))
        ->change('status', 'draft', 'paid')
        ->commit();

    $entry = Entry::first();

    expect($entry->diff['status']['new'])->toBe('paid');
});

it('sorts diff keys deterministically', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('diff.sorted')
        ->subject(ref('ledger'))
        ->diff([
            'b' => ['old' => 1, 'new' => 2],
            'a' => ['old' => 1, 'new' => 2],
        ])
        ->build();

    expect(array_keys($entry['diff']))->toBe(['a', 'b']);
});
