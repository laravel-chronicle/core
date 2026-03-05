<?php

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
     * Generate the chain hash.
     */
    public function hash(string $previousChainHash, string $payloadHash): string
    {
        return hash('sha256', $previousChainHash.$payloadHash);
    }
}
