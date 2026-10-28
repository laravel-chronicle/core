<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom cast whose set() returns an object rather than a stored value,
 * as in the report on #235. Laravel formats it when binding the query.
 *
 * @implements CastsAttributes<CarbonImmutable|null, mixed>
 */
final class ObjectReturningDatetimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
