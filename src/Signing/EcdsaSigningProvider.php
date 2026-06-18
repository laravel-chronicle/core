<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use InvalidArgumentException;
use RuntimeException;

/**
 * ECDSA P-256 signing provider using PHP's OpenSSL extension.
 *
 * Keys are stored as PEM string (the natural format for EC keys).
 * Signatures are base64_encoded DER binary (consistent with Ed25519SigningProvider).
 *
 * Constructed via array config for container makeWith compatibility:
 *   'private_key' => PEM string (optional - omit for verify-only)
 *   'public_key' => PEM string (required)
 *   'key_id' => string identifier
 */
final class EcdsaSigningProvider extends LocalVerifyProvider
{
    protected ?string $privatePem = null;

    protected string $publicPem;

    protected string $keyId;

    /**
     * @param  array{private_key?: string|null, public_key?: string|null, key_id?: string|null}  $config
     */
    public function __construct(array $config = [])
    {
        $publicKey = $config['public_key'] ?? null;

        if (! is_string($publicKey) || $publicKey === '') {
            throw new InvalidArgumentException(
                'Missing public_key for EcdsaSigningProvider - provide a PEM-encoded EC public key.'
            );
        }

        $this->publicPem = $publicKey;

        $privateKey = $config['private_key'] ?? null;

        if (is_string($privateKey) && $privateKey !== '') {
            $this->privatePem = $privateKey;
        }

        $this->keyId = $config['key_id'] ?? 'none';
    }

    public function sign(string $payload): string
    {
        if ($this->privatePem === null) {
            throw new RuntimeException(
                'Cannot sign: EcdsaSigningProvider was configured with no private key (verify-only).'
            );
        }

        $signature = '';
        $result = openssl_sign($payload, $signature, $this->privatePem, OPENSSL_ALGO_SHA256);

        if ($result === false || ! is_string($signature)) {
            $error = openssl_error_string();

            throw new RuntimeException(
                'ECDSA P-256 signing failed'.(is_string($error) ? ': '.$error : '.')
            );
        }

        return base64_encode($signature);
    }

    public function algorithm(): string
    {
        return 'ecdsa-p256';
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    protected function cachedPublicKeyPem(): string
    {
        return $this->publicPem;
    }
}
