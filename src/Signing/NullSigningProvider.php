<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use Chronicle\Contracts\SigningProvider;
use RuntimeException;
use Throwable;

final readonly class NullSigningProvider implements SigningProvider
{
    public function __construct(
        protected Throwable $originalException,
    ) {
        //
    }

    public function sign(string $payload): string
    {
        throw new RuntimeException(
            'Chronicle signing is not configured. Set CHRONICLE_PRIVATE_KEY and CHRONICLE_PUBLIC_KEY.',
            0,
            $this->originalException,
        );
    }

    public function verify(string $payload, string $signature): bool
    {
        throw new RuntimeException(
            'Chronicle signing is not configured. Set CHRONICLE_PRIVATE_KEY and CHRONICLE_PUBLIC_KEY.',
            0,
            $this->originalException,
        );
    }

    public function algorithm(): string
    {
        return 'none';
    }

    public function keyId(): ?string
    {
        return null;
    }
}
