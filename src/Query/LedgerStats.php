<?php

namespace Chronicle\Query;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public static function compute(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): self {
        /** @var string|null $configured */
        $configured = config('chronicle.connection');

        $db = is_string($configured) && $configured !== ''
            ? DB::connection($configured)
            : DB::connection();

        /** @var string $entriesTable */
        $entriesTable = config('chronicle.tables.entries', 'chronicle_entries');

        /** @var string $checkpointsTable */
        $checkpointsTable = config('chronicle.tables.checkpoints', 'chronicle_checkpoints');

        $baseQuery = $db->table($entriesTable);

        if ($from !== null) {
            $baseQuery->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $baseQuery->where('created_at', '<=', $to);
        }

        $aggregate = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_entries, MIN(created_at) as oldest_entry_at, MAX(created_at) as newest_entry_at')
            ->first();

        if ($aggregate === null) {
            return new self(
                totalEntries: 0,
                oldestEntryAt: null,
                newestEntryAt: null,
                checkpointCount: 0,
                topActions: [],
                dailyActivity: [],
            );
        }

        /** @var int $totalEntries */
        $totalEntries = $aggregate->total_entries;

        /** @var string|null $rawOldest */
        $rawOldest = $aggregate->oldest_entry_at;
        $oldestEntryAt = $rawOldest !== null
            ? Carbon::parse($rawOldest)
            : null;

        /** @var string|null $rawNewest */
        $rawNewest = $aggregate->newest_entry_at;
        $newestEntryAt = $rawNewest !== null
            ? Carbon::parse($rawNewest)
            : null;

        /** @var list<array{action: string, count: int}> $topActions */
        $topActions = (clone $baseQuery)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function (object $row): array {
                /** @var string $action */
                $action = $row->action;
                /** @var int|string $count */
                $count = $row->count;

                return ['action' => $action, 'count' => (int) $count];
            })
            ->values()
            ->all();

        /** @var list<array{date: string, count: int}> $dailyActivity */
        $dailyActivity = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(function (object $row): array {
                /** @var string $date */
                $date = $row->date;
                /** @var int|string $count */
                $count = $row->count;

                return ['date' => $date, 'count' => (int) $count];
            })
            ->values()
            ->all();

        $cpQuery = $db->table($checkpointsTable);

        if ($from !== null) {
            $cpQuery->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $cpQuery->where('created_at', '<=', $to);
        }

        $checkpointCount = $cpQuery->count();

        return new self(
            totalEntries: $totalEntries,
            oldestEntryAt: $oldestEntryAt,
            newestEntryAt: $newestEntryAt,
            checkpointCount: $checkpointCount,
            topActions: $topActions,
            dailyActivity: $dailyActivity,
        );
    }
}
