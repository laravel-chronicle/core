<?php

namespace Chronicle\Encryption;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A wrapped per-subject Data Encryption Key (DEK).
 *
 * One row per (subject_type, subject_id). `wrapped_dek` is the DEK encrypted
 * under the KEK. Erasure nulls `wrapped_dek` and sets status='erased' — the
 * row survives as a tombstone so an erased subject can never mint a new key.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $wrapped_dek
 * @property string $kek_id
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $erased_at
 */
class SubjectKey extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'subject_type',
        'subject_id',
        'wrapped_dek',
        'kek_id',
        'status',
        'created_at',
        'erased_at',
    ];

    public function getConnectionName(): ?string
    {
        /** @var string|null $configured */
        $configured = config('chronicle.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return parent::getConnectionName();
    }

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('chronicle.tables.subject_keys', 'chronicle_subject_keys');

        return $table;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return string[]
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
        ];
    }

    public function isErased(): bool
    {
        return $this->status === 'erased';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
