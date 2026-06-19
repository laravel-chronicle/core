<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Testing\LedgerSeeder;
use Chronicle\Verification\IntegrityVerifier;

beforeEach(fn () => $this->useEloquentDriver());

it('seeds a valid, verifiable chain of entries', function () {
    $result = LedgerSeeder::make()->count(50)->seed();

    expect($result->entries)->toBe(50)
        ->and($result->checkpoints)->toBe(0)
        ->and(Chronicle::query()->count())->toBe(50)
        ->and(app(IntegrityVerifier::class)->verify()->isValid())->toBeTrue();
});

it('defaults to a system actor and a synthetic subject', function () {
    LedgerSeeder::make()->count(1)->seed();

    $entry = Chronicle::query()->oldest()->first();

    expect($entry->actor_type)->toBe('system')
        ->and($entry->actor_id)->toBe('system')
        ->and($entry->action)->toBe('seed.recorded')
        ->and($entry->subject_id)->toBe('1');
});
