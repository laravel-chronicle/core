<?php

use Chronicle\Context\HostContextResolver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Support\Carbon;

function makeHostPending(mixed $context = []): PendingEntry
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

function makeHostResolver(string|false $hostname): HostContextResolver
{
    return new class($hostname) extends HostContextResolver
    {
        public function __construct(private readonly string|false $h) {}

        protected function hostname(): string|false
        {
            return $this->h;
        }
    };
}

it('returns host as the context key', function () {
    expect(app(HostContextResolver::class)->contextKey())->toBe('host');
});

it('runs in the resolve_context stage', function () {
    expect(app(HostContextResolver::class)->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

it('attaches the hostname to context', function () {
    $entry = makeHostPending();
    makeHostResolver('app-server-01')->process($entry);

    expect($entry->attribute('context')['host'])->toBe(['hostname' => 'app-server-01']);
});

it('records an empty string when gethostname returns false', function () {
    $entry = makeHostPending();
    makeHostResolver(false)->process($entry);

    expect($entry->attribute('context')['host'])->toBe(['hostname' => '']);
});

it('preserves existing context keys', function () {
    $entry = makeHostPending(['tenant_id' => 3]);
    makeHostResolver('web-01')->process($entry);

    expect($entry->attribute('context'))->toHaveKey('tenant_id', 3)
        ->and($entry->attribute('context'))->toHaveKey('host');
});
