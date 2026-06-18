<?php

declare(strict_types=1);

namespace Chronicle\Exports;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exceptions\ExportWriteException;
use JsonException;

/**
 * Signs Chronicle export datasets.
 */
final class ExportSigner
{
    protected SigningProvider $signer;

    public function __construct(SigningProvider $signer)
    {
        $this->signer = $signer;
    }

    /**
     * Sign a canonical payload containing all manifest fields.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function sign(
        string $datasetHash,
        int $entryCount,
        ?string $firstEntryId,
        ?string $lastEntryId,
        ?string $chainHead,
    ): array {
        $canonical = json_encode([
            'dataset_hash' => $datasetHash,
            'entry_count' => $entryCount,
            'first_entry_id' => $firstEntryId,
            'last_entry_id' => $lastEntryId,
            'chain_head' => $chainHead,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $signature = $this->signer->sign($canonical);

        return [
            'signature' => $signature,
            'algorithm' => $this->signer->algorithm(),
            'key_id' => $this->signer->keyId(),
        ];
    }

    /**
     * Write the signature file.
     *
     * @param  array<string, mixed>  $signature
     */
    public function write(string $path, array $signature): void
    {
        try {
            $json = json_encode(
                $signature,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw ExportWriteException::encodeFailed('signature');
        }

        if (@file_put_contents($path, $json) === false) {
            throw ExportWriteException::writeFailed($path);
        }
    }
}
