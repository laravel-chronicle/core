<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('stores payload hash', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.created')
        ->subject('invoice')
        ->commit();

    $entry = Entry::first();

    expect($entry->payload_hash)->not->toBeNull();

});
