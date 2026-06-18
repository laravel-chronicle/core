<?php

declare(strict_types=1);

namespace Chronicle\Encryption;

use Chronicle\Contracts\KeyEncryptionProvider;
use Chronicle\Exceptions\EncryptionException;
use Exception;
use SodiumException;

/**
 * Default KEK provider: wraps DEKs under a local secret read from
 * CHRONICLE_ENCRYPTION_KEY (a dedicated base64 32-byte key - never the app
 * key). Uses libsodium secretbox (XSalsa20-Poly1305) for authenticated
 * wrapping.
 */
final class LocalKeyEncryptionProvider implements KeyEncryptionProvider
{
    private string $kek;

    private string $kekId;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $key = $config['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw EncryptionException::missingEncryptionKey();
        }

        $raw = base64_decode($key, true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw EncryptionException::invalidEncryptionKey();
        }

        $this->kek = $raw;

        $id = $config['id'] ?? 'local';
        $this->kekId = is_string($id) && $id !== '' ? $id : 'local';
    }

    /**
     * @throws SodiumException
     * @throws Exception
     */
    public function wrap(string $dek): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($dek, $nonce, $this->kek);

        return base64_encode($nonce.$cipher);
    }

    /**
     * @throws SodiumException
     */
    public function unwrap(string $wrapped): string
    {
        $raw = base64_decode($wrapped, true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw EncryptionException::unwrapFailed();
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $dek = sodium_crypto_secretbox_open($cipher, $nonce, $this->kek);

        if ($dek === false) {
            throw EncryptionException::unwrapFailed();
        }

        return $dek;
    }

    public function kekId(): string
    {
        return $this->kekId;
    }
}
