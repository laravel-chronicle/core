<?php

namespace Chronicle\Query;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class LedgerStats
{
    /**
     * @param  list<array{action: string, count: int}>  $topActions
     * @param  list<array{date: string, count: int}>  $dailyActivity
     */
    public function __construct(
        protected readonly int $totalEntries,
        protected readonly ?Carbon $oldestEntryAt,
        protected readonly ?Carbon $newestEntryAt,
        protected readonly int $checkpointCount,
        protected readonly array $topActions,
        protected readonly array $dailyActivity,
    ) {
        //
    }

    public function totalEntries(): int
    {
        return $this->totalEntries;
    }

    public function oldestEntryAt(): ?CarbonInterface
    {
        return $this->oldestEntryAt;
    }

    public function newestEntryAt(): ?CarbonInterface
    {
        return $this->newestEntryAt;
    }

    public function checkpointCount(): int
    {
        return $this->checkpointCount;
    }

    /**
     * @return list<array{action: string, count: int}>
     */
    public function topActions(): array
    {
        return $this->topActions;
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function dailyActivity(): array
    {
        return $this->dailyActivity;
    }

    public function isEmpty(): bool
    {
        return $this->totalEntries === 0;
    }
}
