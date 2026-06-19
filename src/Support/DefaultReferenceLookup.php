<?php

declare(strict_types=1);

namespace Chronicle\Support;

use Chronicle\Contracts\ReferenceLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // The query-free body lands here; Task 2 adds the $hydrate branch above
        // this line.
        return $this->defaultLabel($type, $id, $this->classFor($type));
    }

    public function model(string $type, string $id): ?Model
    {
        // Implemented in Task 2.
        return null;
    }

    /**
     * Map a stored type string to a concrete class, honouring the morph map.
     *
     * @return class-string|null
     */
    private function classFor(string $type): ?string
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

    private function defaultLabel(string $type, string $id, ?string $class): string
    {
        if ($type === 'system') {
            return 'System';
        }

        $human = Str::headline(class_basename($class ?? $type));

        return $id === '' ? $human : "$human #$id";
    }
}
