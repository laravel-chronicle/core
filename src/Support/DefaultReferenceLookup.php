<?php

declare(strict_types=1);

namespace Chronicle\Support;

use Chronicle\Contracts\ReferenceLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Default reverse reference resolver. Honors Relation::morphMap() (alias->class),
 * guards unknown classes with class_exists, and humanises a basename + id when
 * the class is unknown. resolve() and the default label() never query; model()
 * and label(hydrate: true) are the only methods that touch the database.
 */
final class DefaultReferenceLookup implements ReferenceLookup
{
    public function resolve(string $type, string $id): ResolvedReference
    {
        $class = $this->classFor($type);

        return new ResolvedReference(
            type: $type,
            class: $class,
            id: $id,
            label: $this->defaultLabel($type, $id, $class),
        );
    }

    public function label(string $type, string $id, bool $hydrate = false): string
    {
        if ($hydrate) {
            $model = $this->model($type, $id);

            if ($model !== null) {
                $attribute = Config::string('chronicle.references.label_attribute', 'name');
                $value = $model->getAttribute($attribute);

                if (is_scalar($value) && (string) $value !== '') {
                    return (string) $value;
                }
            }
        }

        return $this->defaultLabel($type, $id, $this->classFor($type));
    }

    public function model(string $type, string $id): ?Model
    {
        $class = $this->classFor($type);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $instance = new $class;

        return $instance->newQuery()->find($id);
    }

    /**
     * Map a stored type string to a concrete class, honouring the morph map.
     *
     * @return class-string|null
     */
    protected function classFor(string $type): ?string
    {
        $mapped = Relation::getMorphedModel($type);

        if (is_string($mapped) && class_exists($mapped)) {
            return $mapped;
        }

        if (class_exists($type)) {
            return $type;
        }

        return null;
    }

    protected function defaultLabel(string $type, string $id, ?string $class): string
    {
        if ($type === 'system') {
            return 'System';
        }

        $human = Str::headline(class_basename($class ?? $type));

        return $id === '' ? $human : "$human #$id";
    }
}
