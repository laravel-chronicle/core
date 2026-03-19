<?php

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;

class EnvironmentContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'environment';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        /** @var string $name */
        $name = config('app.env') ?: 'unknown';

        return [
            'name' => $name,
            'debug' => (bool) config('app.debug', false),
        ];
    }
}
