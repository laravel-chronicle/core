<?php

namespace Chronicle\Support;

use Chronicle\Contracts\ReferenceResolver;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Class DefaultReferenceResolver
 *
 * Default implementation used by Chronicle.
 *
 * Supports:
 *  - Eloquent models
 *  - Scalar values
 *  - Objects with id property
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
            throw new InvalidArgumentException(
                'Chronicle: scalar values cannot be used as actor or subject references. Pass an Eloquent model or an object with a public $id property.'
            );
        }

        if (is_object($value) && isset($value->id)) {
            $id = (string) $value->id;

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
        /** @var int|null $key */
        $key = $model->getKey();

        if ($key === null) {
            throw new InvalidArgumentException(
                sprintf('Chronicle: model %s has no primary key — pass a persisted model.', $model::class)
            );
        }

        return new Reference(
            $model::class,
            (string) $key,
        );
    }
}
