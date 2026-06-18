<?php

namespace Chronicle\Lifecycle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * A litigation/legal hold on a subject. While an active hold exists
 * (released_at is null), the subject cannot be erased or pruned.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $reason
 * @property string|null $placed_by
 * @property Carbon $placed_at
 * @property Carbon|null $released_at
 */
class LegalHold extends Model
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
        'reason',
        'placed_by',
        'placed_at',
        'released_at',
    ];

    public function getConnectionName(): ?string
    {
        $configured = Config::string('chronicle.connection');

        return $configured !== '' ? $configured : parent::getConnectionName();
    }

    public function getTable(): string
    {
        return Config::string('chronicle.tables.legal_holds', 'chronicle_legal_holds');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return string[]
     */
    protected function casts(): array
    {
        return [
            'placed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<LegalHold>  $query
     * @return Builder<LegalHold>
     */
    public function scopeActiveFor(Builder $query, string $subjectType, string $subjectId): Builder
    {
        return $query
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('released_at');
    }

    public static function isHeld(string $subjectType, string $subjectId): bool
    {
        return self::query()->activeFor($subjectType, $subjectId)->exists();
    }

    public static function place(string $subjectType, string $subjectId, ?string $reason = null, ?string $by = null): self
    {
        $existing = self::query()->activeFor($subjectType, $subjectId)->first();

        if ($existing !== null) {
            return $existing;
        }

        return self::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'placed_by' => $by,
            'placed_at' => Carbon::now('UTC'),
        ]);
    }

    public static function release(string $subjectType, string $subjectId): int
    {
        return self::query()
            ->activeFor($subjectType, $subjectId)
            ->update(['released_at' => Carbon::now('UTC')]);
    }
}
