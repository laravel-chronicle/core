<?php

use Chronicle\Context\RequestContextResolver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

function makeRequestPending(mixed $context = []): PendingEntry
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

// Helper: resolver that bypasses console detection (simulates HTTP context in tests).
function makeHttpResolver(): RequestContextResolver
{
    return new class(app(Request::class)) extends RequestContextResolver
    {
        protected function isRunningInConsole(): bool
        {
            return false;
        }
    };
}

it('returns request as the context key', function () {
    expect(app(RequestContextResolver::class)->contextKey())->toBe('request');
});

it('runs in the resolve_context stage', function () {
    expect(app(RequestContextResolver::class)->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

it('returns the entry unmodified when running in console', function () {
    $resolver = new class(app(Request::class)) extends RequestContextResolver
    {
        protected function isRunningInConsole(): bool
        {
            return true;
        }
    };
    $entry = makeRequestPending();

    $result = $resolver->process($entry);

    expect($result)->toBe($entry)
        ->and($result->attribute('context'))->toBe([]);
});

it('attaches request data when an http request is active', function () {
    $request = Request::create('https://example.com/path', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => 'TestAgent/1.0',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);
    app()->instance('request', $request);

    $entry = makeRequestPending();
    makeHttpResolver()->process($entry);

    $resolved = $entry->attribute('context')['request'];

    expect($resolved)->toHaveKey('ip_address')
        ->toHaveKey('user_agent', 'TestAgent/1.0')
        ->toHaveKey('url')
        ->toHaveKey('method', 'POST')
        ->toHaveKey('request_id');
});

it('uses the x-request-id header when present', function () {
    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'HTTP_X_REQUEST_ID' => 'custom-request-id-123',
    ]);
    app()->instance('request', $request);

    $entry = makeRequestPending();
    makeHttpResolver()->process($entry);

    expect($entry->attribute('context')['request']['request_id'])->toBe('custom-request-id-123');
});

it('generates a uuid when x-request-id header is absent', function () {
    $request = Request::create('https://example.com/', 'GET');
    app()->instance('request', $request);

    $entry = makeRequestPending();
    makeHttpResolver()->process($entry);

    $requestId = $entry->attribute('context')['request']['request_id'];

    expect($requestId)->toBeString()->not->toBeEmpty();
});

it('returns the same request_id for two entries within the same request', function () {
    $request = Request::create('https://example.com/', 'GET');
    app()->instance('request', $request);

    $resolver = makeHttpResolver();
    $entry1 = makeRequestPending();
    $entry2 = makeRequestPending();

    $resolver->process($entry1);
    $resolver->process($entry2);

    $id1 = $entry1->attribute('context')['request']['request_id'];
    $id2 = $entry2->attribute('context')['request']['request_id'];

    expect($id1)->toBe($id2);
});

it('preserves existing context keys', function () {
    $request = Request::create('https://example.com/', 'GET');
    app()->instance('request', $request);

    $entry = makeRequestPending(['tenant_id' => 5]);
    makeHttpResolver()->process($entry);

    expect($entry->attribute('context'))->toHaveKey('tenant_id', 5)
        ->and($entry->attribute('context'))->toHaveKey('request');
});

it('truncates user agent strings longer than 512 characters', function () {
    $longUserAgent = str_repeat('A', 600);

    $request = Request::create('https://example.com/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $longUserAgent,
    ]);
    app()->instance('request', $request);

    $result = makeHttpResolver()->resolve(makeRequestPending());

    expect(strlen($result['user_agent']))->toBeLessThanOrEqual(512);
});

it('strips password and token query parameters from the stored URL', function () {
    $request = Request::create(
        'https://example.com/api?action=export&password=secret&api_token=abc123'
    );
    app()->instance('request', $request);

    $result = makeHttpResolver()->resolve(makeRequestPending());

    expect($result['url'])->not->toContain('secret')
        ->and($result['url'])->not->toContain('abc123')
        ->and($result['url'])->toContain('action=export');
});
