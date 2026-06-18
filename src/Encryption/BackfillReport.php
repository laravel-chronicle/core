<?php

namespace Chronicle\Encryption;

/**
 * Immutable summary of an EncryptBackfiller::run(). `relinked` counts rows whose
 * stored payload_hash/chain_hash/columns actually changed; `changed` is true when
 * any row changed (and therefore a fresh checkpoint is warranted). `headChainHash`
 * is the chain hash of the final entry after the walk (the new ledger head).
 */
final class BackfillReport
{
    public function __construct(
        public readonly int $scanned,
        public readonly int $encrypted,
        public readonly int $relinked,
        public readonly bool $changed,
        public readonly ?string $headChainHash,
        public readonly bool $dryRun,
    ) {
        //
    }
}
