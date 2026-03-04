<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('records an entry using the facade', function () {
    Chronicle::entry()
        ->actor('system')
        ->action('system.started')
        ->subject('application')
        ->record();

    expect(Entry::count())->toBe(1);
});
