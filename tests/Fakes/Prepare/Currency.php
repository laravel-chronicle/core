<?php

declare(strict_types=1);

namespace Chronicle\Tests\Fakes\Prepare;

use Stringable;

/**
 * A money value object whose string form (a formatted amount) differs from
 * the value an application stores (minor units), as in #243.
 */
final readonly class Currency implements Stringable
{
    private function __construct(private int $minor) {}

    public static function fromMinorAmount(int $minor): self
    {
        return new self($minor);
    }

    public function toMinorAmount(): int
    {
        return $this->minor;
    }

    public function __toString(): string
    {
        return '$'.number_format($this->minor / 100, 2);
    }
}
