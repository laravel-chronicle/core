<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionNotAllowedException;
use Illuminate\Support\Str;

class AllowedActionsPolicy extends AbstractPolicy
{
    public function enforce(PendingEntry $entry): void
    {
        /** @var string[] $allowed */
        $allowed = config('chronicle.policy.allowed_actions', []);

        /** @var string $action */
        $action = $entry->attribute('action');

        foreach ($allowed as $pattern) {
            if (Str::is($pattern, $action)) {
                return;
            }
        }

        throw ActionNotAllowedException::notInAllowlist($action);
    }
}
