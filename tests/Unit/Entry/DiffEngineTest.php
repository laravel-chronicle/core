<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('records explicit diff', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
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
        ->action('test')
        ->subject('ledger')
        ->change('status', 'draft', 'paid')
        ->commit();

    $entry = Entry::first();

    expect($entry->diff['status']['new'])->toBe('paid');
});

it('sorts diff keys deterministically', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
        ->diff([
            'b' => ['old' => 1, 'new' => 2],
            'a' => ['old' => 1, 'new' => 2],
        ])
        ->build();

    expect(array_keys($entry['diff']))->toBe(['a', 'b']);
});
