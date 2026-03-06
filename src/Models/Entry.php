<?php

namespace Chronicle\Models;

use Carbon\CarbonInterface;
use Chronicle\Exceptions\ImmutabilityViolationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * The Chronicle audit entry.
 *
 * This model is READ-ONLY after creation.
 * Any attempt to update or delete a persisted
 * entry throws ImmutabilityViolationException.
 *
 * Do not call Entry::create() or new Entry()
 * directly in application code.
 * Use Chronicle::record()->...->commit() exclusively.
 *
 * @property string $id
 * @property string $actor_type
 * @property string $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string,mixed> $payload
 * @property string $payload_hash
 * @property string $chain_hash
 * @property string $checkpoint_id
 * @property string[] $tags
 * @property string $correlation_id
 * @property string[]|null $diff
 * @property Carbon $created_at
 */
class Entry extends Model
{
    use HasUlids;

    /**
     * The connection used by Chronicle at runtime.
     */
    public function getConnectionName(): ?string
    {
        /** @var string|null $configured */
        $configured = config('chronicle.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return parent::getConnectionName();
    }

    /**
     * The table associated with the model.
     * Reads from config so it can be overridden.
     */
    public function getTable(): string
    {
        /** @var string $table */
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        return $table;
    }

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Chronicle entries have created_at only - no updated_at.
     */
    public $timestamps = false;

    /**
     * These columns may be mass-assigned on the initial insert.
     * After insertion, the model is immutable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'payload',
        'payload_hash',
        'diff',
        'chain_hash',
        'checkpoint_id',
        'metadata',
        'tags',
        'correlation_id',
        'context',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return string[]
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'tags' => 'array',
            'diff' => 'array',
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Prevent updates to persisted entries.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw ImmutabilityViolationException::onUpdate();
        }

        return parent::save($options);
    }

    /**
     * Prevent updates via fill + save patterns.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if ($this->exists) {
            throw ImmutabilityViolationException::onUpdate();
        }

        return parent::update($attributes, $options);
    }

    /**
     * Prevent soft or hard deletes.
     */
    public function delete(): ?bool
    {
        throw ImmutabilityViolationException::onDelete();
    }

    /**
     * Prevent force deletes.
     */
    public function forceDelete(): bool
    {
        throw ImmutabilityViolationException::onDelete();
    }

    /**
     * Checkpoint anchoring this entry.
     *
     * @return BelongsTo<Checkpoint, $this>
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'checkpoint_id');
    }

    /**
     * Scope entries for a specific actor.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForActor(Builder $query, Model $actor): Builder
    {
        return $query
            ->where('actor_type', get_class($actor))
            ->where('actor_id', $actor->getKey());
    }

    /**
     * Scope entries for a specific subject.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope entries by action name.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope entries for correlation identifier.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCorrelation(Builder $query, string $id): Builder
    {
        return $query->where('correlation_id', $id);
    }

    /**
     * Scope entries belonging to a workflow tree.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWorkflow(Builder $query, string $rootCorrelation): Builder
    {
        return $query->where('correlation_id', 'like', $rootCorrelation.'%');
    }

    /**
     * Scope entries containing a tag.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope entries containing multiple tags.
     *
     * @param  Builder<$this>  $query
     * @param  list<string>  $tags
     * @return Builder<$this>
     */
    public function scopeWithTags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        return $query;
    }

    /**
     * Scope entries within a time range.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope entries in reverse chronological order.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Cursor paginate entries in ledger order.
     *
     * This ensures stable pagination for large ledgers
     * without using expensive OFFSET queries.
     *
     * @param  Builder<$this>  $query
     * @return CursorPaginator<int, $this>
     */
    public function scopeCursorPaginateLedger(
        Builder $query,
        int $perPage = 50,
        ?string $cursor = null
    ): CursorPaginator {
        return $query
            ->orderBy('id')
            ->cursorPaginate(
                perPage: $perPage,
                cursor: $cursor,
            );
    }

    /**
     * Cursor paginate entries in reverse order.
     *
     * @param  Builder<$this>  $query
     * @return CursorPaginator<int, $this>
     */
    public function scopeCursorPaginateLatest(
        Builder $query,
        int $perPage = 50,
        ?string $cursor = null
    ): CursorPaginator {
        return $query
            ->orderByDesc('id')
            ->cursorPaginate(
                perPage: $perPage,
                cursor: $cursor,
            );
    }

    /**
     * Stream entries in ledger order.
     *
     * This allows processing large audit datasets
     * without loading them fully into memory.
     *
     * @param  Builder<$this>  $query
     * @return LazyCollection<int, $this>
     */
    public function scopeStream(Builder $query): LazyCollection
    {
        return $query
            ->orderBy('id')
            ->cursor();
    }

    /**
     * Stream entries in reverse ledger order.
     *
     * @param  Builder<$this>  $query
     * @return LazyCollection<int, $this>
     */
    public function scopeStreamLatest(Builder $query): LazyCollection
    {
        return $query
            ->orderByDesc('id')
            ->cursor();
    }
}
