<?php

declare(strict_types=1);

namespace Chronicle\Jobs;

use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\ChainHashEntry;
use Chronicle\Storage\DatabaseDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Queued job that persists a serialized Chronicle entry on the dedicated single-worker chronicle queue.
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

            $dbDriver->store($entry->toDatabasePayload());
        });
    }
}
