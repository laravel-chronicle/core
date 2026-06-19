<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

use Chronicle\Support\ResolvedReference;
use Illuminate\Database\Eloquent\Model;

/**
 * Reverse-resolves a stored Chronicle reference (type, id) into a class, a
 * display label, or (opt-in) a hydrated model. The write direction lives in
 * ReferenceResolver and is unaffected. Bind your own implementation to
 * customise resolution or labelling.
 */
interface ReferenceLookup
{
    /**
     * Resolve a stored (type, id) into a descriptor. Never queries the database.
     */
    public function resolve(string $type, string $id): ResolvedReference;

    /**
     * Display label for a stored (type, id). Queries the database only when
     * $hydrate is true (to read the configured label attribute off the model).
     */
    public function label(string $type, string $id, bool $hydrate = false): string;

    /**
     * Opt-in: hydrate the underlying Eloquent model (queries the database).
     * Returns null when the type does not resolve to an Eloquent model or no
     * row exists.
     */
    public function model(string $type, string $id): ?Model;
}
