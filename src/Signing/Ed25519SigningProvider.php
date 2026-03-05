<?php

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use SodiumException;

/**
 * Ed25519 signing provider using libsodium.
 */
class Ed25519SigningProvider implements SigningProvider
{
    protected string $privateKey;

    protected string $publicKey;

    protected string $keyId = 'none';

    public function __construct(string $privateKey, string $publicKey, string $keyId = 'none')
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->keyId = $keyId;
    }

    /**
     * @throws SodiumException
     */
    public function sign(string $payload): string
    {
        /** @var non-empty-string $secretKey */
        $secretKey = base64_decode($this->privateKey);

        $signature = sodium_crypto_sign_detached($payload, $secretKey);

        return base64_encode($signature);
    }

    /**
     * @throws SodiumException
     */
    public function verify(string $payload, string $signature): bool
    {
        /** @var non-empty-string $signature */
        $signature = base64_decode($signature);

        /** @var non-empty-string $publicKey */
        $publicKey = base64_decode($this->publicKey);

        return sodium_crypto_sign_verify_detached(
            $signature,
            $payload,
            $publicKey,
        );
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
