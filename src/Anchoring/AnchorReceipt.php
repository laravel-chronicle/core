<?php

declare(strict_types=1);

namespace Chronicle\Anchoring;

use DateTimeInterface;

/**
 * Immutable result of anchoring a checkpoint with an external sink.
 *
 * `$anchoredAt` is typed `DateTimeInterface` (not `CarbonImmutable`) so a
 * receipt can be built both from `now()->toImmutable()` and from a
 * `CheckpointAnchor` row's `anchored_at` (annotated `Carbon`) without a
 * PHPStan variance error.
 */
final readonly class AnchorReceipt
{
    public function __construct(
        public string $provider,
        public ?string $reference,
        public ?string $proof,
        public DateTimeInterface $anchoredAt,
    ) {
        //
    }
}
