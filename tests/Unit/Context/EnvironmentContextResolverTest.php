<?php

declare(strict_types=1);

use Chronicle\Context\EnvironmentContextResolver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Support\Carbon;

function makeEnvironmentPending(mixed $context = []): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => $context,
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

it('returns environment as the context key', function () {
    expect(app(EnvironmentContextResolver::class)->contextKey())->toBe('environment');
});

it('runs in the resolve_context stage', function () {
    expect(app(EnvironmentContextResolver::class)->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

it('attaches environment name and debug flag to context', function () {
    config(['app.env' => 'production', 'app.debug' => false]);

    $entry = makeEnvironmentPending();
    app(EnvironmentContextResolver::class)->process($entry);

    expect($entry->attribute('context')['environment'])->toBe([
        'name' => 'production',
        'debug' => false,
    ]);
});

it('casts debug to bool', function () {
    config(['app.debug' => '1']);

    $entry = makeEnvironmentPending();
    app(EnvironmentContextResolver::class)->process($entry);

    expect($entry->attribute('context')['environment']['debug'])->toBeBool()->toBeTrue();
});

it('falls back to unknown when app.env is not set', function () {
    config(['app.env' => null]);

    $entry = makeEnvironmentPending();
    app(EnvironmentContextResolver::class)->process($entry);

    expect($entry->attribute('context')['environment']['name'])->toBe('unknown');
});

it('preserves existing context keys', function () {
    $entry = makeEnvironmentPending(['tenant_id' => 99]);
    app(EnvironmentContextResolver::class)->process($entry);

    expect($entry->attribute('context'))->toHaveKey('tenant_id', 99)
        ->and($entry->attribute('context'))->toHaveKey('environment');
});
