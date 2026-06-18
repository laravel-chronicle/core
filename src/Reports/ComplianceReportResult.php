<?php

declare(strict_types=1);

namespace Chronicle\Reports;

use Carbon\Carbon;

/**
 * Immutable result of generating a compliance report.
 */
readonly class ComplianceReportResult
{
    public function __construct(
        public int $entryCount,
        public ?string $chainHead,
        public string $reportHash,
        public string $signature,
        public string $algorithm,
        public ?string $keyId,
        public Carbon $generatedAt,
        public ?Carbon $from,
        public ?Carbon $to,
        public string $html,
    ) {
        //
    }

    public function isEmpty(): bool
    {
        return $this->entryCount === 0;
    }
}
