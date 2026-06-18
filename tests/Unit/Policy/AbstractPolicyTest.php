<?php

declare(strict_types=1);

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\ChronicleException;
use Chronicle\Pipeline\ExtensionStage;
use Chronicle\Policy\AbstractPolicy;

function makePassingPolicy(): AbstractPolicy
{
    return new class extends AbstractPolicy
    {
        public function enforce(PendingEntry $entry): void {}
    };
}

function makeThrowingPolicy(Throwable $e): AbstractPolicy
{
    return new class($e) extends AbstractPolicy
    {
        public function __construct(private readonly Throwable $e) {}

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
