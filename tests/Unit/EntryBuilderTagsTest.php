<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('normalizes tags', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject('test')
        ->tags([' Orders ', 'CHECKOUT'])
        ->build();

    expect($entry['tags'])->toBe(['checkout', 'orders']);
});

it('removes duplicate tags', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject('test')
        ->tags(['orders', 'Orders', 'ORDERS'])
        ->build();

    expect($entry['tags'])->toBe(['orders']);
});

it('sorts tags alphabetically', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject('test')
        ->tags(['billing', 'auth', 'orders'])
        ->build();

    expect($entry['tags'])->toBe(['auth', 'billing', 'orders']);
});

it('filters empty tags', function () {
    $entry = Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject('test')
        ->tags(['orders', '', '   ', 'billing'])
        ->build();

    expect($entry['tags'])->toBe(['billing', 'orders']);
});

it('persists tags when recording entries', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test.event')
        ->subject('test')
        ->tags(['orders', 'billing'])
        ->commit();

    $entry = Entry::first();

    expect($entry->tags)->toBe(['billing', 'orders']);
});
