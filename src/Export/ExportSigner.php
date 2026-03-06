<?php

namespace Chronicle\Export;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exceptions\ExportWriteException;
use JsonException;

/**
 * Signs Chronicle export datasets.
 */
class ExportSigner
{
    protected SigningProvider $signer;

    public function __construct(SigningProvider $signer)
    {
        $this->signer = $signer;
    }

    /**
     * Sign the dataset hash.
     *
     * @return array<string, mixed>
     */
    public function sign(string $datasetHash): array
    {
        $signature = $this->signer->sign($datasetHash);

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
