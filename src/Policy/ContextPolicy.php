<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\RequiredContextMissingException;

class ContextPolicy extends AbstractPolicy
{
    /** @var string[] */
    private readonly array $requiredKeys;

    public function __construct()
    {
        /** @var string[] $keys */
        $keys = config('chronicle.policy.required_context_keys', []);
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
