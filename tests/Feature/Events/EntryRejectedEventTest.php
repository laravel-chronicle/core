<?php

declare(strict_types=1);

use Chronicle\Events\EntryRejected;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Facades\Chronicle;
use Chronicle\Pipeline\EntryExtensionRegistry;
use Chronicle\Pipeline\EntryPipeline;
use Chronicle\Policy\ForbiddenActionsPolicy;
use Chronicle\Validation\ActionValidator;
use Illuminate\Support\Facades\Event;

it('fires EntryRejected when a validation exception is thrown', function () {
    Event::fake([EntryRejected::class]);

    // action without dots fails ActionValidator
    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('nodots')
        ->subject(ref('x'))
        ->commit()
    )->toThrow(ChronicleException::class);

    Event::assertDispatched(EntryRejected::class, function (EntryRejected $event): bool {
        return $event->reason instanceof ChronicleException;
    });
});

it('fires EntryRejected when a policy violation occurs', function () {
    config(['chronicle.extensions' => [
        ActionValidator::class,
        ForbiddenActionsPolicy::class,
    ]]);
    config(['chronicle.policy.forbidden_actions' => ['invoice.sent']]);

    app()->forgetInstance(EntryExtensionRegistry::class);
    app()->forgetInstance(EntryPipeline::class);
    app()->forgetInstance('chronicle');

    Event::fake([EntryRejected::class]);

    expect(fn () => Chronicle::record()
        ->actor('system')
        ->action('invoice.sent')
        ->subject(ref('invoice-1'))
        ->commit()
    )->toThrow(ChronicleException::class);

    Event::assertDispatched(EntryRejected::class);
});

it('re-throws the exception after firing EntryRejected', function () {
    Event::fake([EntryRejected::class]);

    $thrown = null;
    try {
        Chronicle::record()
            ->actor('system')
            ->action('nodots')
            ->subject(ref('x'))
            ->commit();
    } catch (ChronicleException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ChronicleException::class);
    Event::assertDispatched(EntryRejected::class);
});
