<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ActionForbiddenException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Opt-in policy that rejects entries whose action is on the configured denylist.
 */
final class ForbiddenActionsPolicy extends AbstractPolicy
{
    /** @var string[] */
    protected readonly array $forbiddenActions;

    public function __construct()
    {
        /** @var string[] $actions */
        $actions = Config::array('chronicle.policy.forbidden_actions', []);
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
