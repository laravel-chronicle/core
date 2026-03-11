<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('stores payload hash', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.created')
        ->subject('invoice')
        ->commit();

    $entry = Entry::first();

    expect($entry->payload_hash)->not->toBeNull();

});
