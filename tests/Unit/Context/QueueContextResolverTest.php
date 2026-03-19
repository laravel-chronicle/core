<?php

use Chronicle\Context\QueueContextResolver;
use Chronicle\Context\QueueJobContext;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ExtensionStage;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Carbon;

function makeQueuePending(mixed $context = []): PendingEntry
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

function makeQueueResolver(?Job $job = null): QueueContextResolver
{
    $jobContext = new QueueJobContext;

    if ($job !== null) {
        $jobContext->set($job);
    }

    return new QueueContextResolver($jobContext);
}

it('returns queue as the context key', function () {
    expect(makeQueueResolver()->contextKey())->toBe('queue');
});

it('runs in the resolve_context stage', function () {
    expect(makeQueueResolver()->stage())->toBe(ExtensionStage::RESOLVE_CONTEXT);
});

it('returns the entry unmodified when no queue job is active', function () {
    $resolver = makeQueueResolver();
    $entry = makeQueuePending();

    $result = $resolver->process($entry);

    expect($result)->toBe($entry);
    expect($result->attribute('context'))->toBe([]);
});

it('attaches queue metadata when a job is active', function () {
    $job = mock(Job::class);
    $job->allows('getJobId')->andReturn('job-abc-123');
    $job->allows('getConnectionName')->andReturn('redis');
    $job->allows('getQueue')->andReturn('default');

    $entry = makeQueuePending();
    makeQueueResolver($job)->process($entry);

    expect($entry->attribute('context')['queue'])->toBe([
        'job_id' => 'job-abc-123',
        'connection' => 'redis',
        'queue' => 'default',
    ]);
});

it('preserves existing context keys', function () {
    $job = mock(Job::class);
    $job->allows('getJobId')->andReturn('job-xyz');
    $job->allows('getConnectionName')->andReturn('sync');
    $job->allows('getQueue')->andReturn('high');

    $entry = makeQueuePending(['tenant_id' => 11]);
    makeQueueResolver($job)->process($entry);

    expect($entry->attribute('context'))->toHaveKey('tenant_id', 11);
    expect($entry->attribute('context'))->toHaveKey('queue');
});
