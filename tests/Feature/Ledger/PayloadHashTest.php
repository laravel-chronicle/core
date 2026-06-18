<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;

it('stores payload hash', function () {
    Chronicle::record()
        ->actor('system')
        ->action('invoice.created')
        ->subject(ref('invoice'))
        ->commit();

    $entry = Entry::first();

    expect($entry->payload_hash)->not->toBeNull();

});
