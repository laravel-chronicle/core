<?php

use Chronicle\Anchoring\AnchorReceipt;
use Chronicle\Anchoring\CheckpointDigest;
use Chronicle\Anchoring\NullAnchor;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;

beforeEach(fn () => $this->useEloquentDriver());

it('anchors and verifies a checkpoint via its digest', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    $anchor = new NullAnchor;
    $receipt = $anchor->anchor($checkpoint);

    expect($anchor->name())->toBe('null')
        ->and($receipt->proof)->toBe(CheckpointDigest::for($checkpoint))
        ->and($anchor->verify($checkpoint, $receipt))->toBeTrue();
});

it('fails verification when the proof does not match the digest', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    $anchor = new NullAnchor;
    $tampered = new AnchorReceipt('null', null, str_repeat('0', 64), now()->toImmutable());

    expect($anchor->verify($checkpoint, $tampered))->toBeFalse();
});
