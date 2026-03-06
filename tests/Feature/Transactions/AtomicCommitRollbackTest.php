<?php

use Chronicle\Contracts\StorageDriver;
use Chronicle\Facades\Chronicle;
use Chronicle\Models\Entry;
use Illuminate\Support\Facades\DB;

it('rolls back chronicle commit when persistence fails after insert', function () {
    app()->singleton(StorageDriver::class, function () {
        return new class implements StorageDriver
        {
            /**
             * @param  array<string, mixed>  $entry
             */
            public function store(array $entry): Entry
            {
                /** @var string $table */
                $table = config('chronicle.tables.entries', 'chronicle_entries');
                /** @var string|null $connection */
                $connection = config('chronicle.connection');

                DB::connection($connection)->table($table)->insert([
                    'id' => $entry['id'],
                    'actor_type' => $entry['actor_type'],
                    'actor_id' => $entry['actor_id'],
                    'action' => $entry['action'],
                    'subject_type' => $entry['subject_type'],
                    'subject_id' => $entry['subject_id'],
                    'payload' => json_encode($entry['payload']),
                    'payload_hash' => $entry['payload_hash'],
                    'chain_hash' => $entry['chain_hash'],
                    'metadata' => json_encode($entry['metadata']),
                    'context' => json_encode($entry['context']),
                    'tags' => json_encode($entry['tags']),
                    'diff' => json_encode($entry['diff']),
                    'correlation_id' => $entry['correlation_id'],
                    'checkpoint_id' => $entry['checkpoint_id'],
                    'created_at' => $entry['created_at'],
                ]);

                throw new RuntimeException('forcing rollback');
            }
        };
    });

    expect(function () {
        Chronicle::record()
            ->actor('system')
            ->action('atomic.rollback')
            ->subject('ledger')
            ->commit();
    })->toThrow(RuntimeException::class, 'forcing rollback');

    expect(Entry::count())->toBe(0);
});
