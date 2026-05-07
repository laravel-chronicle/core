<?php

namespace Chronicle\Reports;

use Carbon\Carbon;

class ComplianceReportResult
{
    public function __construct(
        public readonly int $entryCount,
        public readonly ?string $chainHead,
        public readonly string $reportHash,
        public readonly string $signature,
        public readonly string $algorithm,
        public readonly ?string $keyId,
        public readonly Carbon $generatedAt,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly string $html,
    ) {
        //
    }

    public function isEmpty(): bool
    {
        return $this->entryCount === 0;
    }
}
