<?php

namespace Chronicle\Contracts;

use Chronicle\Reference;

/**
 * Interface ReferenceResolver
 *
 * Responsible for converting arbitrary values into
 * deterministic Chronicle references.
 */
interface ReferenceResolver
{
    /**
     * Resolve a value into a Chronicle reference.
     */
    public function resolve(mixed $value): Reference;
}
