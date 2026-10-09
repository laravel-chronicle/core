<?php

declare(strict_types=1);

use Chronicle\Jobs\AnchorCheckpointJob;
use Chronicle\Jobs\PersistChronicleEntryJob;

it('ships a default message group in the package config', function () {
    // Without the key in the config file, CHRONICLE_QUEUE_MESSAGE_GROUP is inert.
    expect(config('chronicle.queue.message_group'))->toBe('chronicle');
});

it('sends a message group id when the persist job targets a FIFO queue', function () {
    $options = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');

    // SQS rejects SendMessage on a .fifo queue without a MessageGroupId.
    expect($options)->toHaveKey('MessageGroupId')
        ->and($options['MessageGroupId'])->toBe('chronicle');
});

it('uses the configured message group for the persist job', function () {
    config(['chronicle.queue.message_group' => 'tenant-a-ledger']);

    $options = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');

    expect($options['MessageGroupId'])->toBe('tenant-a-ledger');
});

it('falls back to the default message group when the configured one would be stripped', function (mixed $configured) {
    config(['chronicle.queue.message_group' => $configured]);

    $options = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');

    // SQS rejects an empty MessageGroupId, and a non-string must not reach it
    // either: resolving the group happens inside dispatch(), so throwing here
    // would fail every audited write in the request.
    expect($options['MessageGroupId'])->toBe('chronicle');
})->with([
    'blank' => [''],
    'null' => [null],
    'false' => [false],
    'integer' => [42],
    // SqsQueue array_filters its options, so a falsy string group would be
    // dropped and the send rejected for a missing group.
    'zero string' => ['0'],
    // transform() treats a blank string as null via blank()/trim(), so a
    // whitespace-only group is dropped the same way.
    'spaces' => ['   '],
    'tab' => ["\t"],
]);

it('lets onGroup() override the persist job message group', function () {
    $job = (new PersistChronicleEntryJob(validEntryPayload()))->onGroup('explicit-group');

    $options = sqsOptionsFor($job, 'chronicle.fifo');

    expect($options['MessageGroupId'])->toBe('explicit-group');
});

it('groups every entry together so the hash chain cannot be reordered', function () {
    $first = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');
    $second = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');

    expect($first['MessageGroupId'])->not->toBeEmpty()
        ->and($first['MessageGroupId'])->toBe($second['MessageGroupId']);
});

it('leaves each dispatch its own deduplication id so no entry is silently discarded', function () {
    $first = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');
    $second = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), 'chronicle.fifo');

    // Chronicle deliberately pins no deduplication ID: a stable one would let SQS
    // drop a redelivered audit entry inside its five minute deduplication window.
    expect($first['MessageDeduplicationId'])->not->toBe($second['MessageDeduplicationId']);
});

it('sends a message group id on a standard queue as well', function (object $job) {
    $options = sqsOptionsFor($job, 'chronicle');

    // Deliberate: the group is attached whatever the queue type, exactly as any
    // other job carrying a message group behaves. On a standard AWS queue it is
    // an unused fair-queue tenant marker. Deciding per queue type is not
    // possible from inside the job - the framework resolves the real target
    // afterwards (queue forwarding, routing, the connection default), and a job
    // that guessed "standard" for a queue that turns out to be FIFO would have
    // every send rejected for a missing group.
    expect($options['MessageGroupId'])->not->toBeEmpty();
})->with([
    'persist' => [fn () => new PersistChronicleEntryJob(validEntryPayload())],
    'anchor' => [fn () => new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'rfc3161')],
]);

it('sends a message group id when pushed with no explicit queue name', function () {
    // The connection resolves its own default queue, which may be FIFO. The job
    // cannot see which, so the group is attached either way.
    $options = sqsOptionsFor(new PersistChronicleEntryJob(validEntryPayload()), null, 'chronicle.fifo');

    expect($options['MessageGroupId'])->toBe('chronicle');
});

it('sends a message group id when the anchor job targets a FIFO queue', function () {
    $options = sqsOptionsFor(
        new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'rfc3161'),
        'chronicle-anchors.fifo',
    );

    expect($options)->toHaveKey('MessageGroupId')
        ->and($options['MessageGroupId'])->toBe('01K5H8QK7YB3M0V2E4N6P8R1T3');
});

it('groups anchor jobs by checkpoint so different checkpoints anchor in parallel', function () {
    $first = sqsOptionsFor(new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'rfc3161'), 'anchors.fifo');
    $second = sqsOptionsFor(new AnchorCheckpointJob('01K5H8QNJ2C4P1W3F5Q7S9T2V4', 'rfc3161'), 'anchors.fifo');

    // Anchoring is not order sensitive, so only anchors for the same checkpoint
    // need to serialise - distinct checkpoints get distinct groups.
    expect($first['MessageGroupId'])->not->toBe($second['MessageGroupId']);
});

it('groups every provider for one checkpoint under that checkpoint', function () {
    $first = sqsOptionsFor(new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'rfc3161'), 'anchors.fifo');
    $second = sqsOptionsFor(new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'null'), 'anchors.fifo');

    expect($first['MessageGroupId'])->not->toBeEmpty()
        ->and($first['MessageGroupId'])->toBe($second['MessageGroupId']);
});

it('lets onGroup() override the anchor job message group', function () {
    $job = (new AnchorCheckpointJob('01K5H8QK7YB3M0V2E4N6P8R1T3', 'rfc3161'))->onGroup('anchor-group');

    expect(sqsOptionsFor($job, 'anchors.fifo')['MessageGroupId'])->toBe('anchor-group');
});
