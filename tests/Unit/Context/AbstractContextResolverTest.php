<?php

use Chronicle\Context\AbstractContextResolver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Support\Carbon;

function makeAbstractResolverPending(mixed $context = []): PendingEntry
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

function makeConcreteResolver(string $key, ?array $result): AbstractContextResolver
{
    return new class($key, $result) extends AbstractContextResolver
    {
        public function __construct(private readonly string $k, private readonly ?array $r) {}

        public function contextKey(): string
        {
            return $this->k;
        }

        public function resolve(PendingEntry $entry): ?array
        {
            return $this->r;
        }
    };
}

// ---------------------------------------------------------------------------
// Stage
// ---------------------------------------------------------------------------

it('runs in the resolve_context stage', function () {
    $resolver = makeConcreteResolver('test', []);

    expect($resolver->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

// ---------------------------------------------------------------------------
// Null-resolve skip
// ---------------------------------------------------------------------------

it('returns the entry unmodified when resolve returns null', function () {
    $resolver = makeConcreteResolver('test', null);
    $entry = makeAbstractResolverPending(['app_key' => 'app_value']);

    $result = $resolver->process($entry);

    expect($result)->toBe($entry)
        ->and($result->attribute('context'))->toBe(['app_key' => 'app_value']);
});

// ---------------------------------------------------------------------------
// Context merge
// ---------------------------------------------------------------------------

it('sets the resolved data under the context key', function () {
    $resolver = makeConcreteResolver('environment', ['name' => 'production']);
    $entry = makeAbstractResolverPending();

    $resolver->process($entry);

    expect($entry->attribute('context'))->toBe(['environment' => ['name' => 'production']]);
});

it('preserves existing application context keys alongside the resolver key', function () {
    $resolver = makeConcreteResolver('host', ['hostname' => 'server-01']);
    $entry = makeAbstractResolverPending(['tenant_id' => 42]);

    $resolver->process($entry);

    expect($entry->attribute('context'))->toBe([
        'tenant_id' => 42,
        'host' => ['hostname' => 'server-01'],
    ]);
});

it('coerces a non-array context attribute to an empty array before merging', function () {
    $resolver = makeConcreteResolver('host', ['hostname' => 'server-01']);
    $entry = makeAbstractResolverPending('not-an-array');

    $resolver->process($entry);

    expect($entry->attribute('context'))->toBe(['host' => ['hostname' => 'server-01']]);
});

it('overwrites the same key if the resolver runs twice', function () {
    $resolver = makeConcreteResolver('host', ['hostname' => 'second']);
    $entry = makeAbstractResolverPending(['host' => ['hostname' => 'first']]);

    $resolver->process($entry);

    expect($entry->attribute('context')['host']['hostname'])->toBe('second');
});
