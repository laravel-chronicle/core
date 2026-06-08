<?php

use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Verification\IntegrityVerifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->useEloquentDriver();
});

it('assigns a contiguous monotonic sequence in commit order', function () {
    foreach (['a.one', 'a.two', 'a.three'] as $action) {
        Chronicle::record()
            ->actor(ref('actor-1'))
            ->action($action)
            ->subject(ref('subject-1'))
            ->commit();
    }

    $sequences = Entry::query()->orderBy('sequence')->pluck('sequence')->all();

    expect($sequences)->toBe([1, 2, 3]);
});

it('orders the chain by sequence so verification passes regardless of ulid sort', function () {
    foreach (range(1, 5) as $i) {
        Chronicle::record()
            ->actor(ref('actor-1'))
            ->action("a.$i")
            ->subject(ref('subject-1'))
            ->commit();
    }

    // Force ULID order to disagree with sequence order: give the first entry
    // (sequence 1) the lexicographically-largest id, simulating a same-millisecond
    // cross-process ULID collision.
    $first = Entry::query()->orderBy('sequence')->first();
    Entry::query()->whereKey($first->id)->update(['id' => (string) Str::ulid()]);

    $verifier = app(IntegrityVerifier::class);
    $result = $verifier->verify();

    expect($result->isValid())->toBeTrue();
});

it('rejects a second entry that forks the chain head', function () {
    Chronicle::record()->actor(ref('a'))->action('a.one')->subject(ref('s'))->commit();

    $head = Entry::query()->orderByDesc('sequence')->first();

    // Attempt to insert a forked row reusing the head's sequence number.
    expect(fn () => DB::table('chronicle_entries')->insert([
        'id' => (string) Str::ulid(),
        'actor_type' => 'x', 'actor_id' => 'x', 'action' => 'fork',
        'subject_type' => 'x', 'subject_id' => 'x',
        'payload' => '{}', 'payload_hash' => str_repeat('0', 64),
        'chain_hash' => str_repeat('1', 64),
        'metadata' => '[]', 'context' => '[]', 'tags' => '[]', 'diff' => null,
        'correlation_id' => null, 'checkpoint_id' => null,
        'sequence' => $head->sequence,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});
