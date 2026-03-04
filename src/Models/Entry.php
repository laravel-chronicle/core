<?php

namespace Chronicle\Models;

use Chronicle\Exceptions\ImmutabilityViolationException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
 */
class Entry extends Model
{
    use HasUlids;

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
        'recorded_at',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
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
            'recorded_at' => 'datetime',
            'metadata' => 'array',
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
}
