<?php

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use InvalidArgumentException;
use RuntimeException;
use SodiumException;

/**
 * Ed25519 signing provider using libsodium.
 */
class Ed25519SigningProvider implements SigningProvider
{
    protected ?string $privateKey = null;

    protected string $publicKey;

    protected string $keyId = 'none';

    /**
     * Positional constructor — kept for direct/test use.
     * Pass a non-empty $config array to use the array-config path instead.
     *
     * @param  array{private_key?: ?string, public_key?: string, key_id?: string}  $config
     */
    public function __construct(
        ?string $privateKey = null,
        ?string $publicKey = null,
        string $keyId = 'none',
        array $config = [],
    ) {
        if ($config !== []) {
            $this->bootFromConfig($config);

            return;
        }

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
     * @param  array{private_key?: ?string, public_key?: string, key_id?: string}  $config
     */
    private function bootFromConfig(array $config): void
    {
        // private_key is optional - null means verify-only
        $rawPrivate = $config['private_key'] ?? null;

        if ($rawPrivate !== null && $rawPrivate !== '') {
            $this->privateKey = $this->decodeBase64(
                $rawPrivate,
                'private_key',
                SODIUM_CRYPTO_SIGN_SECRETKEYBYTES,
            );
        }

        $this->publicKey = $this->decodeBase64(
            $config['public_key'] ?? null,
            'public_key',
            SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES,
        );

        $this->keyId = (string) ($config['key_id'] ?? 'none');
    }

    /**
     * @throws SodiumException
     * @throws RuntimeException when constructed as a verify-only key (no private key)
     */
    public function sign(string $payload): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException(
                'Cannot sign: this Ed25519 provider was configured with no private key (verify-only).'
            );
        }

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

    /**
     * @throws SodiumException
     */
    public function __destruct()
    {
        if ($this->privateKey !== null) {
            sodium_memzero($this->privateKey);
        }
    }
}
