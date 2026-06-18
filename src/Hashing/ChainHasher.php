<?php

declare(strict_types=1);

namespace Chronicle\Hashing;

/**
 * Class ChainHasher
 *
 * Computes the hash linking entries together in the Chronicle ledger.
 *
 * chain_hash = SHA256(previous_chain_hash + payload_hash)
 */
class ChainHasher
{
    /**
     * Seed used as the "previous chain hash" for the first entry in the ledger.
     */
    public const GENESIS = '0';

    /**
     * Generate the chain hash.
     */
    public function hash(string $previousChainHash, string $payloadHash): string
    {
        return hash('sha256', $previousChainHash.$payloadHash);
    }
}
