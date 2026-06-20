<?php

declare(strict_types=1);

namespace Chronicle;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader as LedgerReaderContract;
use Chronicle\Contracts\ReferenceLookup;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Eloquent\ChronicleModelObserver;
use Chronicle\Encryption\SubjectKeyManager;
use Chronicle\Entry\Entry;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Entry\PendingEntry;
use Chronicle\Events\EntryRejected;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Exceptions\InvalidEntryModelException;
use Chronicle\Jobs\PersistChronicleEntryJob;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Query\LedgerQuery;
use Chronicle\Storage\ArrayDriver;
use Chronicle\Storage\DriverResolver;
use Chronicle\Storage\NullDriver;
use Chronicle\Storage\QueuedDriver;
use Chronicle\Support\ResolvedReference;
use Chronicle\Testing\ChronicleAssertions;
use Chronicle\Transaction\ChronicleTransaction;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

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

    protected EntryExtensionRegistry $extensions;

    protected ReferenceLookup $lookup;

    protected ?StorageDriver $resolvedDriver = null;

    protected EntryPipeline $prePipeline;

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
        EntryPipeline $prePipeline,
        LedgerReaderContract $reader,
        DriverResolver $drivers,
        EntryExtensionRegistry $extensions,
        ReferenceLookup $lookup,
    ) {
        $this->resolver = $resolver;
        $this->pipeline = $pipeline;
        $this->prePipeline = $prePipeline;
        $this->reader = $reader;
        $this->drivers = $drivers;
        $this->extensions = $extensions;
        $this->lookup = $lookup;
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

        $this->transactions[] = $correlationId;

        if (! $callback) {
            return $transaction;
        }

        try {
            return $callback($transaction);
        } finally {
            $this->endTransaction();
        }
    }

    /**
     * Pop the most recent transaction from the correlation stack.
     *
     * Called automatically at the end of callback-style transactions.
     * Must be called manually via ChronicleTransaction::end() when
     * using the manual transaction style.
     */
    public function endTransaction(): void
    {
        array_pop($this->transactions);
    }

    public function reader(): LedgerReaderContract
    {
        return $this->reader;
    }

    /**
     * Return a fluent LedgerQuery for the Chronicle entries table.
     *
     * Bypasses LedgerReaderContract intentionally - LedgerQuery builds on the
     * Eloquent builder directly to support its full fluent API (filters, sorts,
     * cursor pagination). LedgerReaderContract is for read-path injection; this
     * shortcut is for application-layer querying.
     */
    public function query(): LedgerQuery
    {
        return new LedgerQuery($this->newEntryQuery());
    }

    /**
     * Resolve the configured Chronicle entry model class.
     *
     * Defaults to Chronicle\Entry\Entry. Hosts may point chronicle.models.entry
     * at a subclass; this validates that the override extends Entry so the
     * immutability and chain contract are preserved.
     *
     * @return class-string<Entry>
     */
    public function entryModel(): string
    {
        $class = Config::string('chronicle.models.entry', Entry::class);

        if (! class_exists($class) || ! is_a($class, Entry::class, true)) {
            throw InvalidEntryModelException::for($class);
        }

        /** @var class-string<Entry> $class */
        return $class;
    }

    /**
     * Start a fresh Eloquent query on the configured entry model.
     *
     * @return Builder<Entry>
     */
    public function newEntryQuery(): Builder
    {
        return $this->entryModel()::query();
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
     * Fires EntryRejected if the entry is rejected by a validator or policy,
     * then re-throws the exception.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws Throwable
     */
    public function commit(array $payload): void
    {
        try {
            $this->runCommit($payload);
        } catch (ChronicleException $e) {
            Event::dispatch(new EntryRejected($e, $payload));

            throw $e;
        }
    }

    /**
     * Crypto-shred a subject: destroy its DEK (rendering all its encrypted
     * payloads permanently unreadable) and record a PII-free, verifiable
     * `subject.erased` proof. Idempotent: returns false if already erased.
     *
     * The proof is recorded AFTER the DEK is destroyed, so EncryptPayload
     * leaves it cleartext (see the erased-subject skip) and it stays readable.
     *
     * @throws Throwable
     */
    public function eraseSubject(
        string $subjectType,
        string $subjectId,
        ?string $requester = null,
        ?string $reason = null,
        bool $legalHoldOverride = false,
    ): bool {
        $keys = app(SubjectKeyManager::class);

        if ($keys->stateFor($subjectType, $subjectId)->erased) {
            return false;
        }

        $keys->destroy($subjectType, $subjectId);

        /** @var array<string, mixed> $metadata */
        $metadata = array_filter([
            'requester' => $requester,
            'reason' => $reason,
            'legal_hold_override' => $legalHoldOverride ? true : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $this->commit([
            'id' => (string) Str::ulid(),
            'actor_type' => 'system',
            'actor_id' => $requester ?? 'system',
            'action' => 'subject.erased',
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'context' => [],
            'diff' => null,
            'tags' => [],
            'correlation_id' => $this->currentCorrelation(),
            'created_at' => Carbon::now('UTC'),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws Throwable
     */
    protected function runCommit(array $payload): void
    {
        $driver = $this->getActiveDriver();

        if ($driver instanceof NullDriver) {
            return;
        }

        if ($driver instanceof QueuedDriver) {
            $entry = new PendingEntry($payload);
            $entry = $this->prePipeline->process($entry);

            $queue = Config::string('chronicle.queue.name', 'chronicle');

            /** @var string|null $connection */
            $connection = Config::get('chronicle.queue.connection');

            $attrs = $entry->toDatabasePayload();

            $job = new PersistChronicleEntryJob($attrs);

            if ($connection !== null && $connection !== '') {
                $job->onConnection($connection);
            }

            dispatch($job->onQueue($queue));

            return;
        }

        /** @var string|null $connection */
        $connection = Config::get('chronicle.connection');

        DB::connection($connection)->transaction(function () use ($payload) {
            $entry = new PendingEntry($payload);

            $this->pipeline->process($entry);
        });
    }

    /**
     * @throws BindingResolutionException
     */
    public function driver(string $name): StorageDriver
    {
        return $this->drivers->resolve($name);
    }

    public function extendDriver(string $name, callable $factory): void
    {
        $this->drivers->extend($name, $factory);
    }

    /**
     * @param  EntryExtension|class-string<EntryExtension>  $extension
     *
     * @throws BindingResolutionException
     */
    public function extendEntry(EntryExtension|string $extension): void
    {
        $this->extensions->register($extension);
    }

    /**
     * @throws BindingResolutionException
     */
    public function getActiveDriver(): StorageDriver
    {
        if ($this->resolvedDriver === null) {
            $driver = Config::string('chronicle.driver', 'eloquent');

            $this->resolvedDriver = $this->drivers->resolve($driver);
        }

        return $this->resolvedDriver;
    }

    /**
     * Swap the active driver directly. Used by fake() and useEloquentDriver() in tests.
     *
     * @internal
     */
    public function swapDriver(StorageDriver $driver): void
    {
        $this->resolvedDriver = $driver;
    }

    /**
     * Reset the resolved driver to null so the next access re-resolves from config.
     * Used by ChronicleAssertions::restore() after Chronicle::fake().
     *
     * @internal
     */
    public function resetDriver(): void
    {
        $this->resolvedDriver = null;
    }

    /**
     * Swap the active driver to ArrayDriver and return a ChronicleAssertions helper.
     *
     * Call at the start of a test. Flushes ArrayDriver storage so entries from
     * previous tests do not leak. Rebuilds the EntryPipeline singleton so that
     * PersistEntry uses ArrayDriver - entries will NOT appear in the database.
     *
     * Example:
     *   $chronicle = Chronicle::fake();
     *   // ... trigger code under test ...
     *   $chronicle->assertRecorded(fn ($e) => $e['action'] === 'invoice.sent');
     */
    public function fake(): ChronicleAssertions
    {
        ArrayDriver::flush();

        $driver = new ArrayDriver;

        // Bind this specific instance so the pipeline resolves it via StorageDriver.
        app()->instance(StorageDriver::class, $driver);

        // Forget the pipeline singleton and rebuild it with the new StorageDriver.
        app()->forgetInstance(EntryPipeline::class);

        /** @var EntryPipeline $pipeline */
        $pipeline = app(EntryPipeline::class);
        $this->pipeline = $pipeline;

        $this->swapDriver($driver);

        return new ChronicleAssertions($driver);
    }

    /**
     * Register a ChronicleModelObserver for a model class.
     *
     * If no observer class is given, the base ChronicleModelObserver is used.
     *
     * @param  class-string<Model>  $model
     * @param  class-string<ChronicleModelObserver>|null  $observer
     */
    public function observe(string $model, ?string $observer = null): void
    {
        $observerInstance = $observer !== null
            ? app($observer)
            : app(ChronicleModelObserver::class);

        assert($observerInstance instanceof ChronicleModelObserver);

        $model::observe($observerInstance);
    }

    /**
     * Reverse-resolve a stored (type, id) reference into a descriptor (class +
     * display label). Never queries the database.
     */
    public function resolveReference(string $type, string $id): ResolvedReference
    {
        return $this->lookup->resolve($type, $id);
    }

    /**
     * Display label for a stored (type, id) reference. Queries the database only
     * when $hydrate is true.
     */
    public function referenceLabel(string $type, string $id, bool $hydrate = false): string
    {
        return $this->lookup->label($type, $id, $hydrate);
    }

    /**
     * Opt-in: hydrate the Eloquent model behind a stored (type, id) reference.
     * Queries the database. Null if the type is not an Eloquent model or no row exists.
     */
    public function referenceModel(string $type, string $id): ?Model
    {
        return $this->lookup->model($type, $id);
    }
}
