<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RequiredContextMissingException;

class ContextPolicy extends AbstractPolicy
{
    public function enforce(PendingEntry $entry): void
    {
        /** @var string[] $requiredKeys */
        $requiredKeys = config('chronicle.policy.required_context_keys', []);

        $context = $entry->attribute('context');
        $context = is_array($context) ? $context : [];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $context)) {
                throw RequiredContextMissingException::missingKey($key);
            }
        }
    }
}
