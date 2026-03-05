<?php

namespace Chronicle\Contracts;

/**
 * Interface SigningProvider
 *
 * Responsible for cryptographically signing
 * Chronicle checkpoint payloads.
 *
 * Chronicle itself does not implement cryptography
 * directly and delegates signing to this provider.
 */
interface SigningProvider
{
    /**
     * Sign a payload.
     */
    public function sign(string $payload): string;

    /**
     * Verify a payload signature.
     */
    public function verify(string $payload, string $signature): bool;

    /**
     * Return algorithm identifier.
     *
     * Example:
     *  - ed25519
     *  - rsa
     *  - kms
     */
    public function algorithm(): string;

    /**
     * Return identifier of the key used.
     *
     * Useful for key rotation.
     */
    public function keyId(): ?string;
}
