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
    public function __construct(
        protected readonly SigningProvider $signer,
        protected readonly CanonicalPayloadSerializer $serializer,
    ) {
        //
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
                VerificationFailure::EntriesMissing->value
            );
        }

        if (! file_exists($manifestPath)) {
            return ExportVerificationResult::failure(
                VerificationFailure::ManifestMissing->value
            );
        }

        if (! file_exists($signaturePath)) {
            return ExportVerificationResult::failure(
                VerificationFailure::SignatureMissing->value
            );
        }

        $manifest = $this->decodeJsonFile(
            path: $manifestPath,
            unreadableFailure: VerificationFailure::ManifestUnreadable->value,
            invalidJsonFailure: VerificationFailure::ManifestInvalidJson->value,
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
            unreadableFailure: VerificationFailure::SignatureUnreadable->value,
            invalidJsonFailure: VerificationFailure::SignatureInvalidJson->value,
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
                VerificationFailure::DatasetHashMismatch->value
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
                VerificationFailure::SignatureInvalid->value
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
            return VerificationFailure::EntriesUnreadable->value;
        }

        $handle = @fopen($entriesPath, 'rb');

        if (! $handle) {
            return VerificationFailure::EntriesUnreadable->value;
        }

        $hashContext = hash_init('sha256');
        $previousChain = '0';

        $first = null;
        $last = null;
        $chainHead = null;
        $count = 0;

        while (($line = fgets($handle)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            hash_update($hashContext, $line);

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                fclose($handle);

                return VerificationFailure::EntriesInvalidJson->value;
            }

            if (! is_array($decoded)) {
                fclose($handle);

                return VerificationFailure::EntriesInvalidFormat->value;
            }

            $entryId = $decoded['id'] ?? null;
            $payloadHash = $decoded['payload_hash'] ?? null;
            $chainHash = $decoded['chain_hash'] ?? null;

            if (! is_string($entryId) || $entryId === '') {
                fclose($handle);

                return VerificationFailure::EntriesInvalidFormat->value;
            }

            if (! is_string($payloadHash) || ! is_string($chainHash)) {
                fclose($handle);

                return VerificationFailure::EntriesInvalidFormat->value;
            }

            $computedChain = hash('sha256', $previousChain.$payloadHash);
            if (! hash_equals($computedChain, $chainHash)) {
                fclose($handle);

                return VerificationFailure::ChainInvalid->value;
            }

            // Re-derive payload hash from the exported payload to detect tampered payload data.
            /** @var array<string, mixed>|null $payload */
            $payload = $decoded['payload'] ?? null;
            if (! is_array($payload)) {
                fclose($handle);

                return VerificationFailure::EntriesInvalidFormat->value;
            }

            try {
                $canonical = $this->serializer->serialize($payload);
            } catch (JsonException) {
                fclose($handle);

                return VerificationFailure::EntriesInvalidFormat->value;
            }

            $computedPayloadHash = hash('sha256', $canonical);
            if (! hash_equals($computedPayloadHash, $payloadHash)) {
                fclose($handle);

                return VerificationFailure::PayloadHashMismatch->value;
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
            return VerificationFailure::EntryCountMismatch->value;
        }

        if ($first !== $manifest['first_entry_id']) {
            return VerificationFailure::FirstEntryMismatch->value;
        }

        if ($last !== $manifest['last_entry_id']) {
            return VerificationFailure::LastEntryMismatch->value;
        }

        if ($chainHead !== $manifest['chain_head']) {
            return VerificationFailure::ChainHeadMismatch->value;
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
            return VerificationFailure::ManifestInvalid->value;
        }

        if (! is_int($entryCount) || $entryCount < 0) {
            return VerificationFailure::ManifestInvalid->value;
        }

        if ($entryCount === 0) {
            if ($firstEntryId !== null || $lastEntryId !== null || $chainHead !== null) {
                return VerificationFailure::ManifestInvalid->value;
            }

            return null;
        }

        if (! is_string($chainHead) || $chainHead === '') {
            return VerificationFailure::ManifestInvalid->value;
        }

        if (! is_string($firstEntryId) || $firstEntryId === '') {
            return VerificationFailure::ManifestInvalid->value;
        }

        if (! is_string($lastEntryId) || $lastEntryId === '') {
            return VerificationFailure::ManifestInvalid->value;
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
            return VerificationFailure::SignatureInvalidFormat->value;
        }

        return null;
    }
}
