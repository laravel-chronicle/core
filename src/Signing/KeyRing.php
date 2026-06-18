<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use Chronicle\Exceptions\UnknownSigningKeyException;

/**
 * Contract for a signing key ring that resolves signing and verifying providers by key id.
 */
interface KeyRing
{
    /**
     * Return the provider for the currently active signing key.
     */
    public function active(): SigningProvider;

    /**
     * Return the provider that can verify an artifact signed with the given
     * algorithm and key ID.
     *
     * @throws UnknownSigningKeyException
     */
    public function resolve(string $algorithm, ?string $keyId): SigningProvider;

    /**
     * Return all configured providers, keyed by "{algorithm}:{keyId}".
     *
     * @return array<string, SigningProvider>
     */
    public function all(): array;
}
