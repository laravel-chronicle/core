<?php

namespace Chronicle\Export;

use Chronicle\Contracts\SigningProvider;

/**
 * Verifies Chronicle export datasets.
 */
class ExportVerifier
{
    protected ExportHasher $hasher;

    protected SigningProvider $signer;

    protected ExportChainVerifier $chainVerifier;

    public function __construct(
        ExportHasher $hasher,
        SigningProvider $signer,
        ExportChainVerifier $chainVerifier
    ) {
        $this->hasher = $hasher;
        $this->signer = $signer;
        $this->chainVerifier = $chainVerifier;
    }

    /**
     * Verify an export directory.
     */
    public function verify(string $path): ExportVerificationResult
    {
        $entriesPath = $path.'/entries.ndjson';
        $manifestPath = $path.'/manifest.json';
        $signaturePath = $path.'/signature.json';

        if (! file_exists($entriesPath)) {
            return ExportVerificationResult::failure(
                'entries_missing'
            );
        }

        if (! file_exists($manifestPath)) {
            return ExportVerificationResult::failure(
                'manifest_missing'
            );
        }

        if (! file_exists($signaturePath)) {
            return ExportVerificationResult::failure(
                'signature_missing'
            );
        }

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        /** @var array<string, mixed> $signature */
        $signature = json_decode((string) file_get_contents($signaturePath), true);

        /*
        |--------------------------------------------------------------------------
        | Dataset Hash Verification
        |--------------------------------------------------------------------------
        */
        $computedHash = $this->hasher->hashFile($entriesPath);

        if ($computedHash !== $manifest['dataset_hash']) {
            return ExportVerificationResult::failure(
                'dataset_hash_mismatch'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Dataset Hash Verification
        |--------------------------------------------------------------------------
        */
        /** @var string $sign */
        $sign = $signature['signature'];

        $validSignature = $this->signer->verify(
            $manifest['dataset_hash'],
            $sign,
        );

        if (! $validSignature) {
            return ExportVerificationResult::failure(
                'signature_invalid'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Chain Integrity Verification
        |--------------------------------------------------------------------------
        */
        if (! $this->chainVerifier->verify($entriesPath)) {
            return ExportVerificationResult::failure(
                'chain_invalid'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Dataset Boundary Verification
        |--------------------------------------------------------------------------
        */
        $boundary = $this->verifyBoundaries($entriesPath, $manifest);

        if ($boundary !== true) {
            return ExportVerificationResult::failure($boundary);
        }

        /** @var int $count */
        $count = $manifest['entry_count'];

        /** @var string $chain */
        $chain = $manifest['chain_head'];

        return ExportVerificationResult::success(
            entryCount: $count,
            datasetHash: $manifest['dataset_hash'],
            chainHead: $chain,
        );
    }

    /**
     * Verify dataset boundaries to prevent truncation attacks.
     *
     * @param  array<string, mixed>  $manifest
     */
    protected function verifyBoundaries(
        string $entriesPath,
        array $manifest,
    ): true|string {
        if (
            ! array_key_exists('first_entry_id', $manifest)
            || ! array_key_exists('last_entry_id', $manifest)
        ) {
            return 'manifest_missing_range';
        }

        $handle = fopen($entriesPath, 'r');

        if (! $handle) {
            return 'entries_unreadable';
        }

        $first = null;
        $last = null;
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            /** @var array<string, mixed> $entry */
            $entry = json_decode($line, true);

            if ($count === 0) {
                $first = $entry['id'];
            }

            $last = $entry['id'];

            $count++;
        }

        fclose($handle);

        if ($count !== $manifest['entry_count']) {
            return 'entry_count_mismatch';
        }

        if ($first !== $manifest['first_entry_id']) {
            return 'first_entry_mismatch';
        }

        if ($last !== $manifest['last_entry_id']) {
            return 'last_entry_mismatch';
        }

        return true;
    }
}
