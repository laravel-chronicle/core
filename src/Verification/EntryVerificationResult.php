<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;

class EntryVerificationResult
{
    private function __construct(
        private readonly bool $valid,
        public readonly ?Entry $entry,
        private readonly ?string $failureCode,
        public readonly ?string $missingId,
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
