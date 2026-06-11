<?php

namespace Chronicle\Encryption;

use Chronicle\Exceptions\EncryptionException;

/**
 * A self-describing AEAD cipher envelope: the on-payload representation of an
 * encrypted field. `_chronicle_enc` marks the format version; nonce and
 * ciphertext are base64. The Phase B pipeline attaches the subject reference
 * alongside this when embedding it in an entry payload.
 */
final class CipherEnvelope
{
    public const VERSION = 'v1';

    public const MARKER = '_chronicle_enc';

    public function __construct(
        public readonly string $nonce,
        public readonly string $ciphertext,
        public readonly string $version = self::VERSION,
    ) {
        //
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            self::MARKER => $this->version,
            'nonce' => $this->nonce,
            'ciphertext' => $this->ciphertext,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! self::isEnvelope($data)) {
            throw EncryptionException::notAnEnvelope();
        }

        $nonce = $data['nonce'] ?? null;
        $ciphertext = $data['ciphertext'] ?? null;

        if (! is_string($nonce) || ! is_string($ciphertext)) {
            throw EncryptionException::malformedEnvelope();
        }

        /** @var string $version */
        $version = $data[self::MARKER];

        return new self($nonce, $ciphertext, $version);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function isEnvelope(array $data): bool
    {
        return is_string($data[self::MARKER] ?? null);
    }
}
