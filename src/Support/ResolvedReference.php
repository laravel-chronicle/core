<?php

declare(strict_types=1);

namespace Chronicle\Support;

/**
 * The reverse of a stored Chronicle reference: a (type, id) pair resolved into
 * a concrete class (when known) and a display label. Produced without touching
 * the database - model hydration is a separate, opt-in step.
 */
final readonly class ResolvedReference
{
    /**
     * @param  string  $type  The stored type string as given (a morph alias or FQCN).
     * @param  class-string|null  $class  The resolved class, or null when unknown/missing.
     * @param  string  $id  The stored identifier.
     * @param  string  $label  A human-readable, query-free display label.
     */
    public function __construct(
        public string $type,
        public ?string $class,
        public string $id,
        public string $label,
    ) {}

    /**
     * Whether the type resolved to an existing class.
     */
    public function exists(): bool
    {
        return $this->class !== null;
    }
}
