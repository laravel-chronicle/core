<?php

namespace Chronicle\Anchoring;

use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ORM mapping for an external-anchor receipt row. Phase A is storage-only;
 * the anchoring provider pipeline that writes/reads these rows arrives in Phase C.
 *
 * @property string $id
 * @property string $checkpoint_id
 * @property string $provider
 * @property string|null $reference
 * @property string|null $proof
 * @property string $status
 * @property Carbon|null $anchored_at
 * @property Carbon $created_at
 */
class CheckpointAnchor extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'checkpoint_id',
        'provider',
        'reference',
        'proof',
        'status',
        'anchored_at',
        'created_at',
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
        $table = config('chronicle.tables.checkpoint_anchors', 'chronicle_checkpoint_anchors');

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
            'anchored_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Checkpoint, $this>
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class, 'checkpoint_id');
    }
}
