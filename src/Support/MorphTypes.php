<?php

declare(strict_types=1);

namespace Chronicle\Support;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The names a stored actor or subject type can go by: a model's class name
 * and its morph alias.
 *
 * Entries written before `chronicle.references.use_morph_map` was turned on
 * carry the class name and later ones the alias, so every lookup by type -
 * the forActor()/forSubject() scopes, subject keys, legal holds - matches
 * both, whichever way the option is set.
 *
 * @internal
 */
final class MorphTypes
{
    /**
     * The type as given, plus its class name when it is a morph alias and its
     * alias when it is a mapped class name.
     *
     * @return non-empty-list<string>
     */
    public static function variants(string $type): array
    {
        $variants = [$type];

        $class = Relation::getMorphedModel($type);

        if (is_string($class)) {
            $variants[] = $class;
        }

        $alias = array_search($type, Relation::morphMap(), true);

        if (is_string($alias)) {
            $variants[] = $alias;
        }

        return array_values(array_unique($variants));
    }

    /**
     * The names a model's entries can be stored under.
     *
     * @return non-empty-list<string>
     */
    public static function forModel(Model $model): array
    {
        return array_values(array_unique([...self::variants($model::class), self::morphClass($model)]));
    }

    /**
     * The model's morph class: its alias, or its class name when it has none.
     * An unmapped model under an enforced morph map falls back to its class
     * name instead of throwing, so the entry is still written.
     */
    public static function morphClass(Model $model): string
    {
        try {
            return $model->getMorphClass();
        } catch (ClassMorphViolationException) {
            return $model::class;
        }
    }
}
