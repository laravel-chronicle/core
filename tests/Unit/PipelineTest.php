<?php

use Chronicle\Contracts\EntryProcessor;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\PendingEntry;
use Chronicle\Models\Entry;
use Chronicle\Pipeline\CanonicalizePayload;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Pipeline\PersistEntry;
use Chronicle\Serialization\CanonicalPayloadSerializer;
use Illuminate\Support\Carbon;

function makePipelinePending(array $overrides = []): PendingEntry
{
    return new PendingEntry(array_merge([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'system',
        'actor_id' => 'system',
        'action' => 'test.event',
        'subject_type' => 'system',
        'subject_id' => 'system',
        'metadata' => ['b' => 2, 'a' => 1],
        'context' => ['z' => true, 'a' => false],
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ], $overrides));
}

it('processes pipeline stages in sequence', function () {
    $entry = makePipelinePending();
    $processorA = mock(EntryProcessor::class);
    $processorB = mock(EntryProcessor::class);

    $processorA
        ->shouldReceive('process')
        ->once()
        ->ordered()
        ->with($entry)
        ->andReturn($entry);

    $processorB
        ->shouldReceive('process')
        ->once()
        ->ordered()
        ->with($entry)
        ->andReturn($entry);

    $pipeline = new EntryPipeline([$processorA, $processorB]);

    expect($pipeline->process($entry))->toBe($entry);
});

it('canonicalizes payload deterministically', function () {
    $entry = makePipelinePending();
    $processor = new CanonicalizePayload(new CanonicalPayloadSerializer);

    $processed = $processor->process($entry);
    $payload = $processed->payload();

    expect($payload)->toBeArray()
        ->and(array_key_first($payload))->toBe('action')
        ->and(array_keys($payload['context']))->toBe(['a', 'z'])
        ->and(array_keys($payload['metadata']))->toBe(['a', 'b']);
});

it('persists entry using storage driver', function () {
    $entry = makePipelinePending();
    $entry->setPayload(['action' => 'test.event']);

    $driver = mock(StorageDriver::class);
    $driver
        ->shouldReceive('store')
        ->once()
        ->withArgs(function (array $payload): bool {
            return $payload['id'] === '01J2Q5M2M8M0P0X2A9BTD3M7D1'
                && $payload['action'] === 'test.event'
                && $payload['payload'] === ['action' => 'test.event'];
        })
        ->andReturn(new Entry);

    $processor = new PersistEntry($driver);

    expect($processor->process($entry))->toBe($entry);
});
