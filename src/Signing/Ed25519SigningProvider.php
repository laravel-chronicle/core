<?php

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use InvalidArgumentException;
use SodiumException;

/**
 * Ed25519 signing provider using libsodium.
 */
class Ed25519SigningProvider implements SigningProvider
{
    protected string $privateKey;

    protected string $publicKey;

    protected string $keyId = 'none';

    public function __construct(?string $privateKey, ?string $publicKey, string $keyId = 'none')
    {
        $this->privateKey = $this->decodeBase64(
            $privateKey,
            'CHRONICLE_PRIVATE_KEY',
            SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
        );
        $this->publicKey = $this->decodeBase64(
            $publicKey,
            'CHRONICLE_PUBLIC_KEY',
            SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        );
        $this->keyId = $keyId;
    }

    /**
     * @throws SodiumException
     */
    public function sign(string $payload): string
    {
        /** @var non-empty-string $privateKey */
        $privateKey = $this->privateKey;

        $signature = sodium_crypto_sign_detached($payload, $privateKey);

        return base64_encode($signature);
    }

    /**
     * @throws SodiumException
     */
    public function verify(string $payload, string $signature): bool
    {
        $signature = base64_decode($signature, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        /** @var non-falsy-string $signature */
        $signature = $signature;
        /** @var non-empty-string $publicKey */
        $publicKey = $this->publicKey;

        return sodium_crypto_sign_verify_detached(
            $signature,
            $payload,
            $publicKey,
        );
    }

    private function decodeBase64(?string $encoded, string $envName, int $expectedBytes): string
    {
        if (! is_string($encoded) || $encoded === '') {
            throw new InvalidArgumentException(
                sprintf('Missing %s: set the environment variable to a base64-encoded key.', $envName)
            );
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid %s: value must be valid base64.', $envName)
            );
        }

        if (strlen($decoded) !== $expectedBytes) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid %s: expected %d bytes after base64 decode, got %d.',
                    $envName,
                    $expectedBytes,
                    strlen($decoded)
                )
            );
        }

        return $decoded;
    }

    public function algorithm(): string
    {
        return 'ed25519';
    }

    public function keyId(): string
    {
        return $this->keyId;
    }
}
