<?php

declare(strict_types=1);

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Anchoring\NullAnchor;

it('is disabled by default and resolves configured providers', function () {
    config(['chronicle.anchoring.enabled' => false, 'chronicle.anchoring.providers' => []]);
    expect(app(AnchorManager::class)->enabled())->toBeFalse();

    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => [
            'null' => ['provider' => NullAnchor::class],
        ],
    ]);

    $manager = app(AnchorManager::class);

    expect($manager->enabled())->toBeTrue()
        ->and($manager->providerNames())->toBe(['null'])
        ->and($manager->provider('null'))->toBeInstanceOf(NullAnchor::class);
});

it('throws for an unknown provider name', function () {
    config(['chronicle.anchoring.providers' => []]);

    expect(fn () => app(AnchorManager::class)->provider('missing'))
        ->toThrow(InvalidArgumentException::class);
});
