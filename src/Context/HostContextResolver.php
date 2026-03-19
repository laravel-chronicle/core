<?php

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;

class HostContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'host';
    }

    public function resolve(PendingEntry $entry): ?array
    {
        return [
            'hostname' => (string) $this->hostname(),
        ];
    }

    protected function hostname(): string|false
    {
        return gethostname();
    }
}
