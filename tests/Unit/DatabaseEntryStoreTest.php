<?php

use Chronicle\Models\Entry;
use Chronicle\Storage\DatabaseEntryStore;

it('persists entries in the database', function () {
    $store = new DatabaseEntryStore;

    $payload = [

        'id' => '01HTESTENTRY',
        'recorded_at' => now(),

        'actor_type' => 'user',
        'actor_id' => '1',

        'action' => 'invoice.created',

        'subject_type' => 'invoice',
        'subject_id' => '10',

        'metadata' => null,
        'context' => null,
        'diff' => null,
        'tags' => null,
        'correlation_id' => null,
    ];

    $store->append($payload);

    expect(Entry::count())->toBe(1);
});
