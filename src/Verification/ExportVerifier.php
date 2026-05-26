<?php

namespace Chronicle\Verification;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

/**
 * Verifies Chronicle export datasets.
 */
class ExportVerifier
{
    protected SigningProvider $signer;

    protected CanonicalPayloadSerializer $serializer;

    public function __construct(
        SigningProvider $signer,
        CanonicalPayloadSerializer $serializer,
    ) {
        $this->signer = $signer;
        $this->serializer = $serializer;
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

        $manifest = $this->decodeJsonFile(
            path: $manifestPath,
            unreadableFailure: 'manifest_unreadable',
            invalidJsonFailure: 'manifest_invalid_json'
        );
        if (is_string($manifest)) {
            return ExportVerificationResult::failure($manifest);
        }

        $manifestValidationFailure = $this->validateManifest($manifest);
        if ($manifestValidationFailure !== null) {
            return ExportVerificationResult::failure($manifestValidationFailure);
        }

        /** @var string $manifestDatasetHash */
        $manifestDatasetHash = $manifest['dataset_hash'];
        /** @var int $manifestEntryCount */
        $manifestEntryCount = $manifest['entry_count'];
        /** @var string|null $manifestChainHead */
        $manifestChainHead = $manifest['chain_head'];

        $signature = $this->decodeJsonFile(
            path: $signaturePath,
            unreadableFailure: 'signature_unreadable',
            invalidJsonFailure: 'signature_invalid_json'
        );
        if (is_string($signature)) {
            return ExportVerificationResult::failure($signature);
        }

        $signatureValidationFailure = $this->validateSignature($signature);
        if ($signatureValidationFailure !== null) {
            return ExportVerificationResult::failure($signatureValidationFailure);
        }

        $entriesInspection = $this->inspectEntries($entriesPath, $manifest);
        if (is_string($entriesInspection)) {
            return ExportVerificationResult::failure($entriesInspection);
        }

        /*
        |--------------------------------------------------------------------------
        | Dataset Hash Verification
        |--------------------------------------------------------------------------
        */
        $computedHash = $entriesInspection['dataset_hash'];

        if (! hash_equals($computedHash, $manifestDatasetHash)) {
            return ExportVerificationResult::failure(
                'dataset_hash_mismatch'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Signature Verification
        |--------------------------------------------------------------------------
        */
        /** @var string $sign */
        $sign = $signature['signature'];

        $validSignature = $this->signer->verify(
            $manifestDatasetHash,
            $sign,
        );

        if (! $validSignature) {
            return ExportVerificationResult::failure(
                'signature_invalid'
            );
        }

        return ExportVerificationResult::success(
            entryCount: $manifestEntryCount,
            datasetHash: $manifestDatasetHash,
            chainHead: $manifestChainHead,
        );
    }

    /**
     * Verify dataset boundaries to prevent truncation attacks.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{dataset_hash: string}|string
     */
    protected function inspectEntries(
        string $entriesPath,
        array $manifest,
    ): array|string {
        if (! is_file($entriesPath) || ! is_readable($entriesPath)) {
            return 'entries_unreadable';
        }

        $handle = @fopen($entriesPath, 'rb');

        if (! $handle) {
            return 'entries_unreadable';
        }

        $hashContext = hash_init('sha256');
        $previousChain = '0';

        $first = null;
        $last = null;
        $chainHead = null;
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            hash_update($hashContext, $line);

            if (trim($line) === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                fclose($handle);

                return 'entries_invalid_json';
            }

            if (! is_array($decoded)) {
                fclose($handle);

                return 'entries_invalid_format';
            }

            $entryId = $decoded['id'] ?? null;
            $payloadHash = $decoded['payload_hash'] ?? null;
            $chainHash = $decoded['chain_hash'] ?? null;

            if (! is_string($entryId) || $entryId === '') {
                fclose($handle);

                return 'entries_invalid_format';
            }

            if (! is_string($payloadHash) || ! is_string($chainHash)) {
                fclose($handle);

                return 'entries_invalid_format';
            }

            $computedChain = hash('sha256', $previousChain.$payloadHash);
            if (! hash_equals($computedChain, $chainHash)) {
                fclose($handle);

                return 'chain_invalid';
            }

            // Re-derive payload hash from the exported payload to detect tampered payload data.
            /** @var array<string, mixed>|null $payload */
            $payload = $decoded['payload'] ?? null;
            if (! is_array($payload)) {
                fclose($handle);

                return 'entries_invalid_format';
            }

            try {
                $canonical = $this->serializer->serialize($payload);
            } catch (JsonException) {
                fclose($handle);

                return 'entries_invalid_format';
            }

            $computedPayloadHash = hash('sha256', $canonical);
            if (! hash_equals($computedPayloadHash, $payloadHash)) {
                fclose($handle);

                return 'payload_hash_mismatch';
            }

            if ($count === 0) {
                $first = $entryId;
            }

            $last = $entryId;
            $chainHead = $chainHash;
            $previousChain = $chainHash;

            $count++;
        }

        fclose($handle);

        $datasetHash = hash_final($hashContext);

        if ($count !== $manifest['entry_count']) {
            return 'entry_count_mismatch';
        }

        if ($first !== $manifest['first_entry_id']) {
            return 'first_entry_mismatch';
        }

        if ($last !== $manifest['last_entry_id']) {
            return 'last_entry_mismatch';
        }

        if ($chainHead !== $manifest['chain_head']) {
            return 'chain_head_mismatch';
        }

        return [
            'dataset_hash' => $datasetHash,
        ];
    }

    /**
     * @return array<string, mixed>|string
     */
    protected function decodeJsonFile(
        string $path,
        string $unreadableFailure,
        string $invalidJsonFailure,
    ): array|string {
        if (! is_file($path) || ! is_readable($path)) {
            return $unreadableFailure;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return $unreadableFailure;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $invalidJsonFailure;
        }

        if (! is_array($decoded)) {
            return $invalidJsonFailure;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function validateManifest(array $manifest): ?string
    {
        $datasetHash = $manifest['dataset_hash'] ?? null;
        $entryCount = $manifest['entry_count'] ?? null;
        $chainHead = $manifest['chain_head'] ?? null;
        $firstEntryId = $manifest['first_entry_id'] ?? null;
        $lastEntryId = $manifest['last_entry_id'] ?? null;

        if (! is_string($datasetHash) || $datasetHash === '') {
            return 'manifest_invalid';
        }

        if (! is_int($entryCount) || $entryCount < 0) {
            return 'manifest_invalid';
        }

        if ($entryCount === 0) {
            if ($firstEntryId !== null || $lastEntryId !== null || $chainHead !== null) {
                return 'manifest_invalid';
            }

            return null;
        }

        if (! is_string($chainHead) || $chainHead === '') {
            return 'manifest_invalid';
        }

        if (! is_string($firstEntryId) || $firstEntryId === '') {
            return 'manifest_invalid';
        }

        if (! is_string($lastEntryId) || $lastEntryId === '') {
            return 'manifest_invalid';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $signature
     */
    protected function validateSignature(array $signature): ?string
    {
        $value = $signature['signature'] ?? null;

        if (! is_string($value) || $value === '') {
            return 'signature_invalid_format';
        }

        return null;
    }
}
