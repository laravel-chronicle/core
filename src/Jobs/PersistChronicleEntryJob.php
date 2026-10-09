<?php

declare(strict_types=1);

namespace Chronicle\Jobs;

use Chronicle\Entry\PendingEntry;
use Chronicle\Events\EntryRecorded;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Storage\DatabaseDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Queued job that persists a serialized Chronicle entry on the dedicated chronicle queue.
 *
 * The chain hash is order-sensitive, so the queue must preserve dispatch order:
 * either a single worker, or a FIFO queue, where messageGroup() keeps every entry
 * in one message group and SQS itself serialises them.
 */
final class PersistChronicleEntryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        protected readonly array $attributes,
    ) {
        //
    }

    /**
     * The SQS message group every Chronicle entry is dispatched under.
     *
     * FIFO queues (such as a Laravel Cloud managed FIFO queue) reject any message
     * without a group, and order messages only within a group. The hash chain is
     * a single global sequence, so every entry MUST share one group - splitting
     * them would let entries be persisted out of order.
     *
     * Attached whatever the queue type. The job cannot tell whether its target
     * is FIFO: the framework resolves the real queue after this runs (queue
     * forwarding, routing, or the connection's default queue), so a job that
     * guessed "standard" for a queue that turned out to be FIFO would have every
     * send rejected for a missing group. On a standard AWS queue the group is
     * merely an unused fair-queue tenant marker.
     *
     * Overridden by ->onGroup() on the dispatched job, which the framework reads
     * from the $messageGroup property in preference to this method.
     */
    public function messageGroup(): string
    {
        $group = Config::get('chronicle.queue.message_group');

        // Resolved inside dispatch(), so this must never throw: a non-string
        // value (CHRONICLE_QUEUE_MESSAGE_GROUP=null yields null, not the config
        // default) would otherwise fail every audited write.
        //
        // The value must also survive the framework on the way to SQS, which
        // discards it two different ways: transform() drops a blank string via
        // trim(), and array_filter() drops a falsy one such as '0'. Either would
        // leave a FIFO send with no group at all, which SQS rejects, so anything
        // that would not survive falls back to the documented name.
        return is_string($group) && trim($group) !== '' && $group !== '0'
            ? $group
            : 'chronicle';
    }

    /**
     * @throws Throwable
     */
    public function handle(ChainHashEntry $chainHasher, DatabaseDriver $dbDriver): void
    {
        /** @var string|null $connection */
        $connection = Config::get('chronicle.connection');

        DB::connection($connection)->transaction(function () use ($chainHasher, $dbDriver): void {
            $entry = new PendingEntry($this->attributes);

            /** @var array<string, mixed> $payload */
            $payload = $this->attributes['payload'];

            /** @var string $payloadHash */
            $payloadHash = $this->attributes['payload_hash'];

            $entry->setPayload($payload);
            $entry->setPayloadHash($payloadHash);

            $entry = $chainHasher->process($entry);

            $stored = $dbDriver->store($entry->toDatabasePayload());

            if ($stored->exists) {
                Event::dispatch(new EntryRecorded($stored));
            }
        });
    }
}
