<?php

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Policy\AbstractPolicy;
use Illuminate\Support\Carbon;

function makePolicyPending(): PendingEntry
{
    return new PendingEntry([
        'id' => '01J2Q5M2M8M0P0X2A9BTD3M7D1',
        'actor_type' => 'App\\Models\\User',
        'actor_id' => '42',
        'action' => 'order.placed',
        'subject_type' => 'App\\Models\\Order',
        'subject_id' => '7',
        'metadata' => [],
        'context' => [],
        'diff' => null,
        'tags' => [],
        'correlation_id' => null,
        'created_at' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
    ]);
}

function makePassingPolicy(): AbstractPolicy
{
    return new class extends AbstractPolicy
    {
        public function enforce(PendingEntry $entry): void {}
    };
}

function makeThrowingPolicy(\Throwable $e): AbstractPolicy
{
    return new class($e) extends AbstractPolicy
    {
        public function __construct(private \Throwable $e) {}

        public function enforce(PendingEntry $entry): void
        {
            throw $this->e;
        }
    };
}

it('runs in the policy stage', function () {
    expect(makePassingPolicy()->stage())->toBe(ExtensionStage::POLICY);
});

it('returns the entry unmodified when enforce passes', function () {
    $entry = makePolicyPending();
    $result = makePassingPolicy()->process($entry);

    expect($result)->toBe($entry);
});

it('propagates exceptions thrown by enforce', function () {
    $exception = new ChronicleException('rejected');

    expect(fn () => makeThrowingPolicy($exception)->process(makePolicyPending()))
        ->toThrow(ChronicleException::class, 'rejected');
});
