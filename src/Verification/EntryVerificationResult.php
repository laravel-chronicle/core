<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;

/**
 * Immutable result of verifying a single entry's hash and chain linkage.
 */
readonly class EntryVerificationResult
{
    protected function __construct(
        protected bool $valid,
        public ?Entry $entry,
        protected ?string $failureCode,
        public ?string $missingId,
    ) {
        //
    }

    public static function ok(Entry $entry): EntryVerificationResult
    {
        return new EntryVerificationResult(
            valid: true,
            entry: $entry,
            failureCode: null,
            missingId: null,
        );
    }

    public static function failure(Entry $entry, string $failureCode): EntryVerificationResult
    {
        return new EntryVerificationResult(
            valid: false,
            entry: $entry,
            failureCode: $failureCode,
            missingId: null,
        );
    }

    public static function notFound(string $id): EntryVerificationResult
    {
        return new EntryVerificationResult(
            valid: false,
            entry: null,
            failureCode: VerificationFailure::NotFound->value,
            missingId: $id,
        );
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function failureCode(): ?string
    {
        return $this->failureCode;
    }
}
