<?php

declare(strict_types=1);

use Chronicle\Context\ProcessContextResolver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Support\Carbon;

function makeProcessPending(mixed $context = []): PendingEntry
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

it('returns process as the context key', function () {
    expect(app(ProcessContextResolver::class)->contextKey())->toBe('process');
});

it('runs in the resolve_context stage', function () {
    expect(app(ProcessContextResolver::class)->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

it('attaches process id, runtime, and php version to context', function () {
    $entry = makeProcessPending();
    app(ProcessContextResolver::class)->process($entry);

    $resolved = $entry->attribute('context')['process'];

    expect($resolved)->toHaveKey('id')
        ->toHaveKey('runtime', 'php')
        ->toHaveKey('version', PHP_VERSION);
});

it('records process id as an integer', function () {
    $entry = makeProcessPending();
    app(ProcessContextResolver::class)->process($entry);

    expect($entry->attribute('context')['process']['id'])->toBeInt();
});

it('preserves existing context keys', function () {
    $entry = makeProcessPending(['tenant_id' => 7]);
    app(ProcessContextResolver::class)->process($entry);

    expect($entry->attribute('context'))->toHaveKey('tenant_id', 7)
        ->and($entry->attribute('context'))->toHaveKey('process');
});
