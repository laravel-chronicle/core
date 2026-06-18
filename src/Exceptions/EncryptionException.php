<?php

declare(strict_types=1);

namespace Chronicle\Exceptions;

use Chronicle\Contracts\KeyEncryptionProvider;

/**
 * Raised for Chronicle encryption / key-management faults. Messages never
 * contain key material or plaintext payload bytes.
 */
class EncryptionException extends ChronicleException
{
    public static function missingEncryptionKey(): self
    {
        return new self('CHRONICLE_ENCRYPTION_KEY is not set. A dedicated base64 32-byte key is required when encryption is enabled (do NOT reuse the app key).');
    }

    public static function invalidEncryptionKey(): self
    {
        return new self('CHRONICLE_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
    }

    public static function invalidKekProvider(mixed $class): self
    {
        return new self('Chronicle KEK provider ['.get_debug_type($class).'] must implement '.KeyEncryptionProvider::class.'.');
    }

    public static function unwrapFailed(): self
    {
        return new self('Failed to unwrap a subject DEK: the wrapped key is malformed or was wrapped under a different KEK.');
    }

    public static function subjectErased(string $subjectType, string $subjectId): self
    {
        return new self("Subject [{$subjectType}:{$subjectId}] has been erased; its encryption key cannot be recreated.");
    }

    public static function notAnEnvelope(): self
    {
        return new self('Value is not a Chronicle cipher envelope.');
    }

    public static function malformedEnvelope(): self
    {
        return new self('Chronicle cipher envelope is malformed.');
    }

    public static function decryptionFailed(): self
    {
        return new self('Failed to decrypt a Chronicle payload: wrong DEK, AAD mismatch, or tampered ciphertext.');
    }

    public static function invalidDek(): self
    {
        return new self('Invalid DEK length for XChaCha20-Poly1305-IETF (expected 32 bytes).');
    }
}
