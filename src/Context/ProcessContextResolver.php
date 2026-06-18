<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;

/**
 * Context resolver that captures the current process metadata (PID, user).
 */
final class ProcessContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'process';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(PendingEntry $entry): array
    {
        return [
            'id' => (int) getmypid(),
            'runtime' => 'php',
            'version' => PHP_VERSION,
        ];
    }
}
