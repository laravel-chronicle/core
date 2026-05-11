<?php

namespace Chronicle\Storage;

use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use JsonException;

class QueuedDriver implements StorageDriver
{
    use SerializesEntryAttributes;

    /**
     * @param  array<string, mixed>  $entry
     *
     * @throws JsonException
     */
    public function store(array $entry): Entry
    {
        /** @var string $queue */
        $queue = config('chronicle.queue.name', 'chronicle');

        /** @var string|null $connection */
        $connection = config('chronicle.queue.connection');

        $job = new \Chronicle\Jobs\PersistChronicleEntryJob($entry);

        if ($connection !== null && $connection !== '') {
            $job->onConnection($connection);
        }

        dispatch($job->onQueue($queue));

        $model = new Entry;
        $model->forceFill($this->toEntryAttributes($entry));
        $model->exists = true;

        return $model;
    }
}
