<?php

declare(strict_types=1);

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Lifecycle\LegalHold;

beforeEach(fn () => $this->useEloquentDriver());

it('does not prune entries of a held subject but prunes others', function () {
    Chronicle::record()->actor(ref('a'))->action('a.held')->subject(ref('held'))->commit();
    Chronicle::record()->actor(ref('a'))->action('a.free')->subject(ref('free'))->commit();

    LegalHold::place('stdClass', 'held', 'litigation');

    // Cutoff in the future so both entries are in range; neither is anchored.
    $before = now()->addDay()->toDateString();

    $this->artisan('chronicle:prune', ['--before' => $before])->assertExitCode(0);

    expect(Entry::query()->where('action', 'a.held')->exists())->toBeTrue()
        ->and(Entry::query()->where('action', 'a.free')->exists())->toBeFalse();
});
