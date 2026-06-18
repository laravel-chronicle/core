<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Progress marker for resumable verification. Records the last
 * fully-verified checkpoint so `chronicle:verify --resume` can continue
 * from there instead of re-walking from genesis.
 *
 * @property string $id
 * @property string $mode
 * @property string|null $last_checkpoint_id
 * @property int $verified_count
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VerificationRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'mode',
        'last_checkpoint_id',
        'verified_count',
        'status',
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
        $table = config('chronicle.tables.verification_runs', 'chronicle_verification_runs');

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
            'verified_count' => 'integer',
        ];
    }
}
