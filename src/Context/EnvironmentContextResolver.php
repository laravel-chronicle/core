<?php

declare(strict_types=1);

namespace Chronicle\Context;

use Chronicle\Entry\PendingEntry;
use Illuminate\Support\Facades\Config;

/**
 * Context resolver that captures the application environment name and debug flag.
 */
final class EnvironmentContextResolver extends AbstractContextResolver
{
    public function contextKey(): string
    {
        return 'environment';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(PendingEntry $entry): array
    {
        $env = Config::get('app.env');
        $name = is_string($env) && $env !== '' ? $env : 'unknown';

        return [
            'name' => $name,
            'debug' => (bool) Config::get('app.debug', false),
        ];
    }
}
