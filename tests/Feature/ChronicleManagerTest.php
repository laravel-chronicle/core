<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('records an entry through the chronicle manager', function () {
    Chronicle::entry()
        ->actor('user:1')
        ->action('invoice.created')
        ->subject('invoice:10')
        ->record();

    expect(Entry::count())->toBe(1);
});
