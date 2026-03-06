<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('allows manually setting a correlation id', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.manual')
        ->subject('ledger')
        ->correlation('test-correlation')
        ->commit();

    $entry = Entry::first();

    expect($entry->correlation_id)->toBe('test-correlation');
});

it('assigns correlation id when using transaction object', function () {
    $tx = Chronicle::transaction();

    $tx->entry()
        ->actor('system')
        ->action('test.one')
        ->subject('ledger')
        ->commit();

    $tx->entry()
        ->actor('system')
        ->action('test.two')
        ->subject('ledger')
        ->commit();

    $entries = Entry::all();

    expect($entries)
        ->toHaveCount(2)
        ->and($entries[0]->correlation_id)
        ->toBe($entries[1]->correlation_id);
});

it('assigns correlation automatically in transaction closure', function () {
    Chronicle::transaction(function () {
        Chronicle::record()
            ->actor('system')
            ->action('test.one')
            ->subject('ledger')
            ->commit();

        Chronicle::record()
            ->actor('system')
            ->action('test.two')
            ->subject('ledger')
            ->commit();
    });

    $entries = Entry::all();

    expect($entries)
        ->toHaveCount(2)
        ->and($entries[0]->correlation_id)
        ->toBe($entries[1]->correlation_id);
});

it('creates unique correlation ids for separate transactions', function () {
    Chronicle::transaction(function () {
        Chronicle::record()
            ->actor('system')
            ->action('test.a')
            ->subject('ledger')
            ->commit();

    });

    Chronicle::transaction(function () {
        Chronicle::record()
            ->actor('system')
            ->action('test.b')
            ->subject('ledger')
            ->commit();
    });

    $entries = Entry::all();

    expect($entries)
        ->toHaveCount(2)
        ->and($entries[0]->correlation_id)
        ->not
        ->toBe($entries[1]->correlation_id);
});

it('supports nested transactions with hierarchical correlation', function () {
    Chronicle::transaction(function () {

        Chronicle::record()
            ->actor('system')
            ->action('outer')
            ->subject('ledger')
            ->commit();

        Chronicle::transaction(function () {

            Chronicle::record()
                ->actor('system')
                ->action('inner')
                ->subject('ledger')
                ->commit();

        });

    });

    $entries = Entry::orderBy('created_at')->orderBy('id')->get();

    expect($entries)->toHaveCount(2);

    $outer = $entries[0]->correlation_id;
    $inner = $entries[1]->correlation_id;

    expect($inner)->toStartWith($outer.'.');

});

it('allows querying entries by correlation id', function () {
    $tx = Chronicle::transaction();

    $tx->entry()
        ->actor('system')
        ->action('first')
        ->subject('ledger')
        ->commit();

    $tx->entry()
        ->actor('system')
        ->action('second')
        ->subject('ledger')
        ->commit();

    $entries = Entry::correlation($tx->id())->get();

    expect($entries)->toHaveCount(2);

});
