<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('creates a valid chain of entries', function () {
    Chronicle::record()
        ->actor('system')
        ->action('a')
        ->subject('test')
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('b')
        ->subject('test')
        ->commit();

    $entries = Entry::all();

    expect($entries[0]->chain_hash)->not->toBeNull()
        ->and($entries[1]->chain_hash)->not->toBeNull();
});
