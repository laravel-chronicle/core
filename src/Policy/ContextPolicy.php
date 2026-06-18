<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RequiredContextMissingException;
use Illuminate\Support\Facades\Config;

/**
 * Opt-in policy that requires entries to carry the configured context keys.
 */
final class ContextPolicy extends AbstractPolicy
{
    /** @var string[] */
    protected readonly array $requiredKeys;

    public function __construct()
    {
        /** @var string[] $keys */
        $keys = Config::array('chronicle.policy.required_context_keys', []);
        $this->requiredKeys = $keys;
    }

    public function enforce(PendingEntry $entry): void
    {
        $context = $entry->attribute('context');
        $context = is_array($context) ? $context : [];

        foreach ($this->requiredKeys as $key) {
            if (! array_key_exists($key, $context)) {
                throw RequiredContextMissingException::missingKey($key);
            }
        }
    }
}
