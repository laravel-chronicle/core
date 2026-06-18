<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\UnauthenticatedActorException;
use Illuminate\Support\Facades\Auth;

/**
 * Opt-in policy that rejects entries recorded without an authenticated actor.
 */
class OnlyAuthenticatedUsersPolicy extends AbstractPolicy
{
    public function enforce(PendingEntry $entry): void
    {
        if ($this->isRunningInConsole()) {
            return;
        }

        if (! Auth::check()) {
            throw UnauthenticatedActorException::notAuthenticated();
        }
    }

    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }
}
