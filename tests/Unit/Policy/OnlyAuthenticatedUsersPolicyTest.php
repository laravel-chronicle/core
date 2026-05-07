<?php

use Chronicle\Exceptions\UnauthenticatedActorException;
use Chronicle\Policy\OnlyAuthenticatedUsersPolicy;
use Illuminate\Support\Facades\Auth;

// Helper: policy that simulates HTTP context (not console).
function makeAuthPolicy(bool $console = false): OnlyAuthenticatedUsersPolicy
{
    return new class($console) extends OnlyAuthenticatedUsersPolicy
    {
        public function __construct(private readonly bool $console) {}

        protected function isRunningInConsole(): bool
        {
            return $this->console;
        }
    };
}

it('passes when the user is authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);

    makeAuthPolicy()->enforce(makePolicyPending());
});

it('throws when the user is not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);

    expect(fn () => makeAuthPolicy()->enforce(makePolicyPending()))
        ->toThrow(UnauthenticatedActorException::class);
});

it('skips the auth check when running in console', function () {
    Auth::shouldReceive('check')->never();

    makeAuthPolicy(console: true)->enforce(makePolicyPending());
});
