<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionForbiddenException;
use Illuminate\Support\Str;

class ForbiddenActionsPolicy extends AbstractPolicy
{
    public function enforce(PendingEntry $entry): void
    {
        /** @var string[] $forbidden */
        $forbidden = config('chronicle.policy.forbidden_actions', []);

        /** @var string $action */
        $action = $entry->attribute('action');

        foreach ($forbidden as $pattern) {
            if (Str::is($pattern, $action)) {
                throw ActionForbiddenException::matchesDenylist($action);
            }
        }
    }
}
