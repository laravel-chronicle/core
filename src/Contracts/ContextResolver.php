<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

use Chronicle\Entry\PendingEntry;

interface ContextResolver
{
    /**
     * The key under which resolved data is nested in the entry's context attribute.
     */
    public function contextKey(): string;

    /**
     * Resolve the context data for this entry.
     *
     * Return null to skip this resolver silently.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(PendingEntry $entry): ?array;
}
