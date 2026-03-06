<?php

namespace Chronicle\Export;

/**
 * Verifies the hash chain of exported Chronicle entries.
 */
class ExportChainVerifier
{
    /**
     * Verify the chain integrity of the exported dataset.
     */
    public function verify(string $entriesPath): bool
    {
        $handle = fopen($entriesPath, 'r');

        if (! $handle) {
            return false;
        }

        // Keep the same genesis seed used when chain hashes are created.
        $previousHash = '0';

        while (($line = fgets($handle)) !== false) {
            /** @var array<string, mixed> $entry */
            $entry = json_decode($line, true);

            /** @var string $payloadHash */
            $payloadHash = $entry['payload_hash'];
            $chainHash = $entry['chain_hash'];

            $computed = hash('sha256', $previousHash.$payloadHash);

            if ($computed !== $chainHash) {
                fclose($handle);

                return false;
            }

            $previousHash = $chainHash;
        }

        fclose($handle);

        return true;
    }
}
