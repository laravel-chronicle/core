<?php

namespace Chronicle\Tests\Fakes;

use Chronicle\Contracts\SigningProvider;

/**
 * Fake signing provider used for testing.
 */
class FakeSigningProvider implements SigningProvider
{
    public function sign(string $payload): string
    {
        return hash('sha256', 'fake-signature-'.$payload);
    }

    public function verify(string $payload, string $signature): bool
    {
        return $signature === $this->sign($payload);
    }

    public function algorithm(): string
    {
        return 'fake';
    }

    public function keyId(): ?string
    {
        return 'test-key';
    }
}
