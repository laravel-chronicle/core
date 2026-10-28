<?php

declare(strict_types=1);

namespace Chronicle\Eloquent;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnitEnum;

/**
 * Builds a before/after attribute diff for an audited Eloquent model change.
 */
final class ModelDiffBuilder
{
    /**
     * Casts that leave the stored value a plain scalar. Both sides of the diff
     * get the same cast, since a database returns e.g. a boolean as 0/1 or
     * false/true depending on the driver.
     */
    private const SCALAR_CASTS = [
        'bool' => 'bool',
        'boolean' => 'bool',
        'int' => 'int',
        'integer' => 'int',
        'float' => 'float',
        'double' => 'float',
        'real' => 'float',
        'string' => 'string',
    ];

    /**
     * Returns true if the model has dirty fields beyond auto-managed timestamp columns.
     */
    public static function hasRelevantChanges(Model $model): bool
    {
        $timestampKeys = array_filter([
            $model::CREATED_AT ?? 'created_at',
            $model::UPDATED_AT ?? 'updated_at',
        ]);

        return ! empty(array_diff_key($model->getDirty(), array_flip($timestampKeys)));
    }

    /**
     * Build an old/new diff for all dirty fields, excluding ignored ones.
     * Hidden attributes, encrypted casts and any in $redact have their values
     * replaced with "[redacted]" so the fact of the change is audited without
     * leaking the value.
     *
     * Both sides are the value as stored, not as cast: `old` is the raw
     * original, `new` the raw dirty value. A date, enum or Stringable a custom
     * cast's set() leaves behind is turned into the scalar it is stored as.
     *
     * @param  list<string>  $ignoreFields
     * @param  list<string>  $redact
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function build(Model $model, array $ignoreFields, array $redact = []): array
    {
        $timestampKeys = array_filter([
            $model::CREATED_AT ?? 'created_at',
            $model::UPDATED_AT ?? 'updated_at',
        ]);

        $redactKeys = array_merge($model->getHidden(), $redact);

        $dirty = array_diff_key($model->getDirty(), array_flip($ignoreFields));
        $diff = [];

        foreach ($dirty as $field => $newValue) {
            if (in_array($field, $timestampKeys, true)) {
                continue;
            }

            if (in_array($field, $redactKeys, true) || self::isEncrypted($model, $field)) {
                $diff[$field] = ['old' => '[redacted]', 'new' => '[redacted]'];

                continue;
            }

            $diff[$field] = [
                'old' => self::stored($model, $field, $model->getRawOriginal($field)),
                'new' => self::stored($model, $field, $newValue),
            ];
        }

        return $diff;
    }

    /**
     * The value as it is stored, as a scalar where possible.
     */
    private static function stored(Model $model, string $field, mixed $value): mixed
    {
        $cast = $model->getCasts()[$field] ?? null;
        $scalarCast = is_string($cast) ? (self::SCALAR_CASTS[strtolower($cast)] ?? null) : null;

        if ($scalarCast !== null && is_scalar($value)) {
            return match ($scalarCast) {
                'bool' => (bool) $value,
                'int' => (int) $value,
                'float' => (float) $value,
                'string' => (string) $value,
            };
        }

        return match (true) {
            $value instanceof DateTimeInterface => $model->fromDateTime($value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            default => $value,
        };
    }

    /**
     * Whether the field has an encrypted cast: `encrypted`, `encrypted:array`
     * and the like, or the encrypted ArrayObject and Collection casts.
     */
    private static function isEncrypted(Model $model, string $field): bool
    {
        $cast = $model->getCasts()[$field] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        $type = explode(':', $cast, 2)[0];

        return strtolower($type) === 'encrypted'
            || in_array($type, [AsEncryptedArrayObject::class, AsEncryptedCollection::class], true);
    }
}
