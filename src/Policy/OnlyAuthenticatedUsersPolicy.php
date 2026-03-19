<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\UnauthenticatedActorException;
use Illuminate\Support\Facades\Auth;

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
