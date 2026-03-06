<?php

use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;

it('records an entry using the facade', function () {
    Chronicle::record()
        ->actor('system')
        ->action('system.started')
        ->subject('application')
        ->commit();

    /** @var Entry|null $entry */
    $entry = Entry::query()->first();

    expect(Entry::count())->toBe(1)
        ->and($entry)->not->toBeNull()
        ->and($entry?->id)->toBeString()
        ->and($entry?->payload)->toBeArray()
        ->and($entry?->payload['id'] ?? null)->toBe($entry?->id);
});
