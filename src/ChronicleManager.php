<?php

namespace Chronicle;

use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Entry\PendingEntry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Storage\DriverResolver;
use Chronicle\Transaction\ChronicleTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class ChronicleManager
 *
 * The ChronicleManager acts as the central entry point for
 * Chronicle's audit logging system.
 *
 * Responsibilities:
 *  - Create EntryBuilder instances
 *  - Dispatch built entries into the Chronicle processing pipeline
 *
 * The manager itself performs no processing. Instead, it delegates
 * entry handling to the EntryPipeline, which executes a sequence
 * of processors responsible for transforming and persisting the entry.
 */
class ChronicleManager
{
    /**
     * Reference resolver used for actors and subjects.
     */
    protected ReferenceResolver $resolver;

    /**
     * Entry processing pipeline.
     */
    protected EntryPipeline $pipeline;

    protected LedgerReaderContract $reader;

    protected DriverResolver $drivers;

    private ?StorageDriver $resolvedDriver = null;

    /**
     * Active transaction stack.
     *
     * @var array<int|string, mixed>
     */
    protected array $transactions = [];

    /**
     * Create a new Chronicle manager instance.
     */
    public function __construct(
        ReferenceResolver $resolver,
        EntryPipeline $pipeline,
        LedgerReaderContract $reader,
        DriverResolver $drivers,
    ) {
        $this->resolver = $resolver;
        $this->pipeline = $pipeline;
        $this->reader = $reader;
        $this->drivers = $drivers;
    }

    /**
     * Create a new entry builder.
     *
     * Developers use this method to begin building
     * a Chronicle audit entry.
     *
     * Example:
     *
     * Chronicle::record()
     *      ->actor($user)
     *      ->action('invoice.sent')
     *      ->subject($invoice)
     *      ->commit();
     */
    public function record(): EntryBuilder
    {
        return new EntryBuilder(
            resolver: $this->resolver,
            manager: $this,
        );
    }

    public function transaction(?callable $callback = null): mixed
    {
        $correlationId = $this->generateCorrelation();

        $transaction = new ChronicleTransaction(
            manager: $this,
            correlationId: $correlationId,
        );

        if (! $callback) {
            return $transaction;
        }

        $this->transactions[] = $correlationId;

        try {
            return $callback($transaction);
        } finally {
            array_pop($this->transactions);
        }
    }

    public function reader(): LedgerReaderContract
    {
        return $this->reader;
    }

    /**
     * Get the current active transaction.
     */
    public function currentTransaction(): ?ChronicleTransaction
    {
        /** @var string $correlationId */
        $correlationId = end($this->transactions);

        if (! $correlationId) {
            return null;
        }

        return new ChronicleTransaction(
            manager: $this,
            correlationId: $correlationId,
        );
    }

    public function currentCorrelation(): ?string
    {
        /** @var string $correlationId */
        $correlationId = end($this->transactions);

        return $correlationId ?: null;
    }

    protected function generateCorrelation(): string
    {
        $id = (string) Str::uuid();

        $parent = $this->currentCorrelation();

        if (! $parent) {
            return $id;
        }

        return "$parent.$id";
    }

    /**
     * Record an entry payload through the Chronicle pipeline.
     *
     * This method is called internally by EntryBuilder::record().
     *
     * The payload will pass through all configured processors
     * before being persisted.
     *
     * @param  array<string, mixed>  $payload
     */
    public function commit(array $payload): void
    {
        /** @var string|null $connection */
        $connection = config('chronicle.connection');

        DB::connection($connection)->transaction(function () use ($payload) {
            $entry = new PendingEntry($payload);

            $this->pipeline->process($entry);
        });
    }

    public function driver(string $name): StorageDriver
    {
        return $this->drivers->resolve($name);
    }

    public function extendDriver(string $name, callable $factory): void
    {
        $this->drivers->extend($name, $factory);
    }

    public function getActiveDriver(): StorageDriver
    {
        if ($this->resolvedDriver === null) {
            /** @var string $driver */
            $driver = config('chronicle.driver', 'eloquent');

            $this->resolvedDriver = $this->drivers->resolve($driver);
        }

        return $this->resolvedDriver;
    }

    /**
     * Swap the active driver directly. Used by fake() and useEloquentDriver() in tests.
     */
    public function swapDriver(StorageDriver $driver): void
    {
        $this->resolvedDriver = $driver;
    }
}
