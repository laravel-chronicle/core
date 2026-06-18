<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Hashing\ChainHasher;
use JsonException;

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
        if (! is_file($entriesPath) || ! is_readable($entriesPath)) {
            return false;
        }

        $handle = @fopen($entriesPath, 'rb');

        if (! $handle) {
            return false;
        }

        // Keep the same genesis seed used when chain hashes are created.
        $previousHash = ChainHasher::GENESIS;

        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                fclose($handle);

                return false;
            }

            if (! is_array($decoded)) {
                fclose($handle);

                return false;
            }

            $payloadHash = $decoded['payload_hash'] ?? null;
            $chainHash = $decoded['chain_hash'] ?? null;

            if (! is_string($payloadHash) || ! is_string($chainHash)) {
                fclose($handle);

                return false;
            }

            $computed = hash('sha256', $previousHash.$payloadHash);

            if (! hash_equals($computed, $chainHash)) {
                fclose($handle);

                return false;
            }

            $previousHash = $chainHash;
        }

        fclose($handle);

        return true;
    }
}
