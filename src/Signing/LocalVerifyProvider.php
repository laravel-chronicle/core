<?php

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;

/**
 * Abstract base for signing providers that verify locally using a cached public key.
 *
 * Subclasses implement sign() (which may be remote, e.g. AWS KMS) and provide
 * the public key PEM via cachedPublicKeyPem(). Verification is always local -
 * no network call, no private key required - dispatching on algorithm().
 */
abstract class LocalVerifyProvider implements SigningProvider
{
    /**
     * Return the cached public key in PEM format.
     * Used by verify() for local signature validation.
     */
    abstract protected function cachedPublicKeyPem(): string;

    /**
     * Verify a signature locally against the cached public key.
     * Dispatches on algorithm() - currently supports 'ecdsa-p256'.
     */
    final public function verify(string $payload, string $signature): bool
    {
        $sigBytes = base64_decode($signature, true);

        if ($sigBytes === false) {
            return false;
        }

        return match ($this->algorithm()) {
            'ecdsa-p256' => $this->verifyEcdsaP256($payload, $sigBytes),
            default => false,
        };
    }

    protected function verifyEcdsaP256(string $payload, string $sigBytes): bool
    {
        $result = openssl_verify($payload, $sigBytes, $this->cachedPublicKeyPem(), OPENSSL_ALGO_SHA256);

        if ($result === false) {
            return false;
        }

        return $result === 1;
    }
}
