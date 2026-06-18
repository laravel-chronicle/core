<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionNotAllowedException;
use Illuminate\Support\Str;

class AllowedActionsPolicy extends AbstractPolicy
{
    /** @var string[] */
    private readonly array $allowedActions;

    public function __construct()
    {
        /** @var string[] $actions */
        $actions = config('chronicle.policy.allowed_actions', []);
        $this->allowedActions = $actions;
    }

    public function enforce(PendingEntry $entry): void
    {
        /** @var string $action */
        $action = $entry->attribute('action');

        foreach ($this->allowedActions as $pattern) {
            if (Str::is($pattern, $action)) {
                return;
            }
        }

        throw ActionNotAllowedException::notInAllowlist($action);
    }
}
