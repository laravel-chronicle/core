<?php

declare(strict_types=1);

namespace Chronicle\Contracts;

/**
 * Wraps and unwraps per-subject Data Encryption Keys (DEKs) under a
 * Key Encryption Key (KEK). The default implementation derives the KEK from
 * a local secret; alternative implementations may delegate to a KMS/HSM.
 */
interface KeyEncryptionProvider
{
    /** Encrypt a raw DEK under the KEK. Returns an opaque, storable string. */
    public function wrap(string $dek): string;

    /** Decrypt a wrapped DEK produced by wrap(). Throws on tamper/wrong KEK. */
    public function unwrap(string $wrapped): string;

    /** Stable identifier of the KEK that wrapped/unwraps, recorded per subject key. */
    public function kekId(): string;
}
