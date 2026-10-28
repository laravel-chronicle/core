<?php

declare(strict_types=1);

namespace Chronicle\Support;

use Chronicle\Contracts\ReferenceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
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
 *
 * A model's type is its class name, or its morph alias when
 * `chronicle.references.use_morph_map` is on.
 */
final class DefaultReferenceResolver implements ReferenceResolver
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
            /** @var int|string $rawId */
            $rawId = $value->id;

            $id = (string) $rawId;

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
        $key = $model->getKey();

        if ($key === null) {
            throw new InvalidArgumentException(
                sprintf('Chronicle: model %s has no primary key - pass a persisted model.', $model::class)
            );
        }

        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException(
                sprintf('Chronicle: model %s has an unsupported primary key type.', $model::class)
            );
        }

        return new Reference(
            Config::boolean('chronicle.references.use_morph_map', false) ? MorphTypes::morphClass($model) : $model::class,
            (string) $key,
        );
    }
}
