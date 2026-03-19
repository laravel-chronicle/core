<?php

// tests/Feature/Policy/CombinedPoliciesTest.php

use Chronicle\Exceptions\ActionForbiddenException;
use Chronicle\Exceptions\ActionNotAllowedException;
use Chronicle\Facades\Chronicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'chronicle.driver' => 'array',
        'chronicle.policy.allowed_actions' => ['user.*', 'order.*'],
        'chronicle.policy.forbidden_actions' => ['user.deleted'],
    ]);

    Chronicle::extendEntry(\Chronicle\Policy\AllowedActionsPolicy::class);
    Chronicle::extendEntry(\Chronicle\Policy\ForbiddenActionsPolicy::class);
});

it('rejects an action in allowlist that also matches denylist', function () {
    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('user.deleted')
        ->subject('user:1')
        ->commit()
    )->toThrow(ActionForbiddenException::class);
});

it('rejects an action not in the allowlist', function () {
    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('payment.captured')
        ->subject('payment:1')
        ->commit()
    )->toThrow(ActionNotAllowedException::class);
});

it('passes an action that is in the allowlist and not in the denylist', function () {
    Chronicle::record()
        ->actor('system')
        ->action('order.placed')
        ->subject('order:1')
        ->commit();
})->throwsNoExceptions();
