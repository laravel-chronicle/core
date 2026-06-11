<?php

use Chronicle\Anchoring\AnchorReceipt;
use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Anchoring\CheckpointDigest;
use Chronicle\Anchoring\NullAnchor;
use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Contracts\AnchorProvider;
use Chronicle\Facades\Chronicle;
use Chronicle\Jobs\AnchorCheckpointJob;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => $this->useEloquentDriver());

it('dispatches an anchoring job per provider after a checkpoint commits when enabled', function () {
    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => ['null' => ['provider' => NullAnchor::class]],
    ]);
    Queue::fake();

    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    app(CheckpointCreator::class)->create();

    Queue::assertPushed(AnchorCheckpointJob::class, 1);
});

it('does not dispatch anchoring when disabled', function () {
    config(['chronicle.anchoring.enabled' => false]);
    Queue::fake();

    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    app(CheckpointCreator::class)->create();

    Queue::assertNothingPushed();
});

it('writes an anchored row for a successful provider', function () {
    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => ['null' => ['provider' => NullAnchor::class]],
    ]);

    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    app(CheckpointAnchorer::class)->anchor($checkpoint, 'null');

    $row = $checkpoint->anchors()->where('provider', 'null')->firstOrFail();

    expect($row->status)->toBe('anchored')
        ->and($row->proof)->toBe(CheckpointDigest::for($checkpoint))
        ->and($row->anchored_at)->not->toBeNull();
});

it('marks the row failed and rethrows when the provider fails - checkpoint is untouched', function () {
    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => ['boom' => ['provider' => FailingAnchor::class]],
    ]);
    // Fake the queue so create()'s post-commit AnchorCheckpointJob isn't run
    // inline by the sync driver - we drive the failing anchor explicitly below.
    Queue::fake();

    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();
    $checkpoint = app(CheckpointCreator::class)->create();

    expect(fn () => app(CheckpointAnchorer::class)->anchor($checkpoint, 'boom'))
        ->toThrow(RuntimeException::class)
        ->and(Checkpoint::query()->count())->toBe(1)
        ->and($checkpoint->anchors()->where('provider', 'boom')->firstOrFail()->status)->toBe('failed');
});

class FailingAnchor implements AnchorProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config = []) {}

    public function name(): string
    {
        return 'boom';
    }

    public function anchor(Checkpoint $checkpoint): AnchorReceipt
    {
        throw new RuntimeException('anchor sink down');
    }

    public function verify(Checkpoint $checkpoint, AnchorReceipt $receipt): bool
    {
        return false;
    }
}
