<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;

class ProcessContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'process';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        return [
            'id' => (int) getmypid(),
            'runtime' => 'php',
            'version' => PHP_VERSION,
        ];
    }
}
