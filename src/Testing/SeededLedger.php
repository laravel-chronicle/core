<?php

declare(strict_types=1);

namespace Chronicle\Testing;

/**
 * Summary of a LedgerSeeder run: how many entries and checkpoints were written,
 * and the id of the last checkpoint (null when none were created).
 */
final readonly class SeededLedger
{
    public function __construct(
        public int $entries,
        public int $checkpoints,
        public ?string $lastCheckpointId,
    ) {}
}
