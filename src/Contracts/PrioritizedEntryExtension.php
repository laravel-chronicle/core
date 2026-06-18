<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

/**
 * Optional priority contract for deterministic ordering inside a stage.
 */
interface PrioritizedEntryExtension
{
    /**
     * Lower values execute first.
     */
    public function priority(): int;
}
