<?php

declare(strict_types=1);

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\EnclosingCheckpointResolver;

beforeEach(fn () => $this->useEloquentDriver());

/**
 * Record 2 entries per segment, capping each with a checkpoint.
 * For N segments: entries 1..2N; checkpoint head sequences are 2,4,...,2N.
 *
 * @return array<int, Checkpoint> oldest-first
 *
 * @throws Throwable
 */
function seedSegments(int $segments): array
{
    $checkpoints = [];
    foreach (range(1, $segments) as $s) {
        Chronicle::record()->actor(ref('a'))->action("s$s.one")->subject(ref('x'))->commit();
        Chronicle::record()->actor(ref('a'))->action("s$s.two")->subject(ref('x'))->commit();
        $checkpoints[] = app(CheckpointCreator::class)->create();
    }

    return $checkpoints;
}

it('resolves the enclosing start checkpoint (latest head < fromSequence)', function () {
    [$c0, $c1, $c2] = seedSegments(3); // heads at seq 2, 4, 6

    $resolver = new EnclosingCheckpointResolver;

    // fromSequence 3 sits just after c0 (head 2): start anchor is c0.
    expect($resolver->start(3)?->id)->toBe($c0->id)
        ->and($resolver->headSequence($c0))->toBe(2)
        // fromSequence 5 sits just after c1 (head 4): start anchor is c1.
        ->and($resolver->start(5)?->id)->toBe($c1->id)
        // fromSequence 2 == c0 head, so no checkpoint head is strictly < 2: genesis.
        ->and($resolver->start(2))->toBeNull()
        // fromSequence 1: genesis.
        ->and($resolver->start(1))->toBeNull();
});

it('resolves the enclosing end checkpoint (earliest head >= toSequence)', function () {
    [$c0, $c1, $c2] = seedSegments(3); // heads at seq 2, 4, 6

    $resolver = new EnclosingCheckpointResolver;

    expect($resolver->end(3)?->id)->toBe($c1->id)   // earliest head >= 3 is c1 (head 4)
        ->and($resolver->end(4)?->id)->toBe($c1->id) // head 4 == toSequence
        ->and($resolver->end(6)?->id)->toBe($c2->id);
});

it('returns null end checkpoint when the range extends past the last checkpoint', function () {
    seedSegments(2); // heads at seq 2, 4
    // Two unanchored entries after the last checkpoint (sequences 5, 6).
    Chronicle::record()->actor(ref('a'))->action('tail.one')->subject(ref('x'))->commit();
    Chronicle::record()->actor(ref('a'))->action('tail.two')->subject(ref('x'))->commit();

    $resolver = new EnclosingCheckpointResolver;

    expect($resolver->end(5))->toBeNull()
        ->and($resolver->end(6))->toBeNull();
});
