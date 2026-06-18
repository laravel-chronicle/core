<?php

declare(strict_types=1);

namespace Chronicle\Checkpoints;

use Chronicle\Anchoring\CheckpointAnchor;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\ImmutabilityViolationException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Represents a cryptographic anchor in the Chronicle ledger.
 *
 * A checkpoint signs a specific chain hash, preventing attackers
 * from recomputing the ledger after tampering.
 *
 * Checkpoints are immutable once created.
 *
 * @property string $id
 * @property string $chain_hash
 * @property string $signature
 * @property string $algorithm
 * @property string $key_id
 * @property string|null $head_id
 * @property int|null $entry_count
 * @property string|null $previous_checkpoint_id
 * @property Carbon $created_at
 */
class Checkpoint extends Model
{
    use HasUlids;

    /**
     * The connection used by Chronicle at runtime.
     */
    public function getConnectionName(): ?string
    {
        $configured = Config::get('chronicle.connection');

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
        return Config::string('chronicle.tables.checkpoints', 'chronicle_checkpoints');
    }

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Chronicle checkpoints have created_at only - no updated_at.
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
        'chain_hash',
        'signature',
        'algorithm',
        'key_id',
        'head_id',
        'entry_count',
        'previous_checkpoint_id',
        'metadata',
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
            'entry_count' => 'integer',
            'metadata' => 'array',
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
     * Prevent updates via fill and save patterns.
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
     * Entries anchored by this checkpoint.
     *
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'checkpoint_id');
    }

    /**
     * The checkpoint immediately preceding this one in the checkpoint chain.
     *
     * @return BelongsTo<Checkpoint, $this>
     */
    public function previousCheckpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'previous_checkpoint_id');
    }

    /**
     * External anchor receipts for this checkpoint.
     *
     * @return HasMany<CheckpointAnchor, $this>
     */
    public function anchors(): HasMany
    {
        return $this->hasMany(CheckpointAnchor::class, 'checkpoint_id');
    }
}
