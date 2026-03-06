<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('returns null when no transaction exists', function () {
    expect(Chronicle::currentTransaction())->toBeNull();
});

it('returns current transaction inside transaction block', function () {
    Chronicle::transaction(function () {
        $tx = Chronicle::currentTransaction();

        expect($tx)->not->toBeNull();

        $tx->entry()
            ->actor('system')
            ->action('test')
            ->subject('ledger')
            ->commit();
    });

    expect(Entry::count())->toBe(1);
});

it('uses same correlation for current transaction', function () {
    Chronicle::transaction(function () {

        Chronicle::record()
            ->actor('system')
            ->action('a')
            ->subject('ledger')
            ->commit();

        Chronicle::currentTransaction()?->entry()
            ->actor('system')
            ->action('b')
            ->subject('ledger')
            ->commit();
    });

    $entries = Entry::all();

    expect($entries[0]->correlation_id)
        ->toBe($entries[1]->correlation_id);
});
