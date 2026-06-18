<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Exceptions\EncryptionException;
use Chronicle\Support\CanonicalPayloadSerializer;
use Exception;
use JsonException;
use SodiumException;

/**
 * AEAD encrypt/decrypt of a payload field set under a subject DEK using
 * XChaCha20-Poly1305-IETF (decision D5) with a fresh per-call 192-bit nonce.
 * The cleartext entry envelope is passed as Associated Data (AAD) so a
 * ciphertext cannot be transplanted to another entry. Verification never
 * decrypts - this class is only used on the write/read paths.
 */
final class PayloadCipher
{
    public function __construct(
        private readonly CanonicalPayloadSerializer $serializer,
    ) {
        //
    }

    /**
     * Encrypt a field set. AAD binds the envelope to its entry.
     *
     * @param  array<string, mixed>  $plaintext
     *
     * @throws SodiumException
     * @throws Exception
     */
    public function encrypt(array $plaintext, string $dek, string $aad): CipherEnvelope
    {
        $this->assertDek($dek);

        $message = $this->serializer->serialize($plaintext);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($message, $aad, $nonce, $dek);

        sodium_memzero($message);

        return new CipherEnvelope(base64_encode($nonce), base64_encode($ciphertext));
    }

    /**
     * Decrypt an envelope. Throws (never returns partial/garbage) on a wrong
     * DEK, AAD mismatch, or tampered ciphertext.
     *
     * @return array<string, mixed>
     *
     * @throws SodiumException
     * @throws JsonException
     */
    public function decrypt(CipherEnvelope $envelope, string $dek, string $aad): array
    {
        $this->assertDek($dek);

        $nonce = base64_decode($envelope->nonce, true);
        $ciphertext = base64_decode($envelope->ciphertext, true);

        if ($nonce === false
            || $ciphertext === false
            || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw EncryptionException::decryptionFailed();
        }

        $message = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $aad, $nonce, $dek);

        if ($message === false) {
            throw EncryptionException::decryptionFailed();
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } finally {
            sodium_memzero($message);
        }

        return $decoded;
    }

    /**
     * Build the canonical Associated Data binding an envelope to its entry:
     * (id, subject_type, subject_id, action). The SAME builder must produce the
     * AAD on encrypt and decrypt, so they never drift. `sequence` is deliberately
     * excluded: encryption runs before the chain assigns a sequence (and the
     * queued driver assigns it in a deferred job), so it is unavailable at
     * encrypt-time. The per-entry ULID `id` already makes the AAD unique, so a
     * ciphertext still cannot be transplanted to another entry.
     *
     * @throws JsonException
     */
    public static function aad(string $id, ?string $subjectType, ?string $subjectId, string $action): string
    {
        return json_encode([
            'action' => $action,
            'id' => $id,
            'subject_id' => $subjectId,
            'subject_type' => $subjectType,
        ], JSON_THROW_ON_ERROR);
    }

    private function assertDek(string $dek): void
    {
        if (strlen($dek) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw EncryptionException::invalidDek();
        }
    }
}
