<?php

namespace Chronicle\Support;

use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Reference;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Class DefaultReferenceResolver
 *
 * Default implementation used by Chronicle.
 *
 * Supports:
 *  - Eloquent models
 *  - scalar values
 *  - objects with id property
 */
class DefaultReferenceResolver implements ReferenceResolver
{
    /**
     * Resolve a value into a Chronicle reference.
     */
    public function resolve(mixed $value): Reference
    {
        if ($value instanceof Model) {
            return $this->resolveModel($value);
        }

        if (is_scalar($value)) {
            return new Reference(
                gettype($value),
                (string) $value,
            );
        }

        if (is_object($value) && isset($value->id)) {
            /** @var string $id */
            $id = $value->id;

            return new Reference(
                $value::class,
                $id,
            );
        }

        throw new InvalidArgumentException(
            'Unable to resolve Chronicle reference.'
        );
    }

    /**
     * Resolve an Eloquent model reference.
     */
    protected function resolveModel(Model $model): Reference
    {
        /** @var string $id */
        $id = $model->getKey();

        return new Reference(
            $model::class,
            $id,
        );
    }
}
