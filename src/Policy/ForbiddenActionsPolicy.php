<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionForbiddenException;
use Illuminate\Support\Str;

class ForbiddenActionsPolicy extends AbstractPolicy
{
    /** @var string[] */
    private readonly array $forbiddenActions;

    public function __construct()
    {
        /** @var string[] $actions */
        $actions = config('chronicle.policy.forbidden_actions', []);
        $this->forbiddenActions = $actions;
    }

    public function enforce(PendingEntry $entry): void
    {
        /** @var string $action */
        $action = $entry->attribute('action');

        foreach ($this->forbiddenActions as $pattern) {
            if (Str::is($pattern, $action)) {
                throw ActionForbiddenException::matchesDenylist($action);
            }
        }
    }
}
