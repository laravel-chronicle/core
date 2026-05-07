<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('creates a valid chain of entries', function () {
    Chronicle::record()
        ->actor('system')
        ->action('chain.first')
        ->subject(ref('test'))
        ->commit();

    Chronicle::record()
        ->actor('system')
        ->action('chain.second')
        ->subject(ref('test'))
        ->commit();

    $entries = Entry::all();

    expect($entries[0]->chain_hash)->not->toBeNull()
        ->and($entries[1]->chain_hash)->not->toBeNull();
});
