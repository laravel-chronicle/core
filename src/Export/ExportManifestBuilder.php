<?php

namespace Chronicle\Export;

use Chronicle\Contracts\SigningProvider;

/**
 * Builds the Chronicle export manifest.
 */
class ExportManifestBuilder
{
    protected SigningProvider $signer;

    public function __construct(SigningProvider $signer)
    {
        $this->signer = $signer;
    }

    /**
     * Build the manifest structure.
     *
     * @return array<string, mixed>
     */
    public function build(
        int $entryCount,
        ?string $chainHead,
        string $datasetHash,
        ?string $firstEntryId,
        ?string $lastEntryId,
    ): array {
        return [
            'version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'entry_count' => $entryCount,
            'first_entry_id' => $firstEntryId,
            'last_entry_id' => $lastEntryId,
            'chain_head' => $chainHead,
            'dataset_hash' => $datasetHash,
            'algorithm' => $this->signer->algorithm(),
        ];
    }

    /**
     * Write the manifest to disk.
     *
     * @param  array<string,mixed>  $manifest
     */
    public function write(string $path, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($path, $json);
    }
}
