<?php

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
final class AnchorReceipt
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $reference,
        public readonly ?string $proof,
        public readonly DateTimeInterface $anchoredAt,
    ) {
        //
    }
}
