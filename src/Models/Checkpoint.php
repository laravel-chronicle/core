<?php

namespace Chronicle\Models;

use Chronicle\Exceptions\ImmutabilityViolationException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a cryptographic anchor in the Chronicle ledger.
 *
 * A checkpoint signs a specific chain hash, preventing attackers
 * from recomputing the ledger after tampering.
 *
 * Checkpoints are immutable once created.
 *
 * @property string $chain_hash
 * @property string $signature
 * @property string $algorithm
 * @property string $key_id
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
        $table = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

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
        'chain_hash',
        'signature',
        'algorithm',
        'key_id',
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
     * Entries anchored by this checkpoint.
     *
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'checkpoint_id');
    }
}
