<?php

declare(strict_types=1);

use Chronicle\Exceptions\ActionForbiddenException;
use Chronicle\Exceptions\ActionNotAllowedException;
use Chronicle\Facades\Chronicle;
use Chronicle\Policy\AllowedActionsPolicy;
use Chronicle\Policy\ForbiddenActionsPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'chronicle.driver' => 'array',
        'chronicle.policy.allowed_actions' => ['user.*', 'order.*'],
        'chronicle.policy.forbidden_actions' => ['user.deleted'],
    ]);

    Chronicle::extendEntry(AllowedActionsPolicy::class);
    Chronicle::extendEntry(ForbiddenActionsPolicy::class);
});

it('rejects an action in allowlist that also matches denylist', function () {
    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('user.deleted')
        ->subject(ref('user:1'))
        ->commit()
    )->toThrow(ActionForbiddenException::class);
});

it('rejects an action not in the allowlist', function () {
    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('payment.captured')
        ->subject(ref('payment:1'))
        ->commit()
    )->toThrow(ActionNotAllowedException::class);
});

it('passes an action that is in the allowlist and not in the denylist', function () {
    Chronicle::record()
        ->actor('system')
        ->action('order.placed')
        ->subject(ref('order:1'))
        ->commit();
})->throwsNoExceptions();
