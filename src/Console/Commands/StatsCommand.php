<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Query\LedgerStats;
use Illuminate\Console\Command;
use JsonException;

/**
 * Artisan command that displays Chronicle ledger statistics.
 */
final class StatsCommand extends Command
{
    protected $signature = 'chronicle:stats
        {--json : Output stats as JSON instead of formatted text}';

    protected $description = 'Display Chronicle ledger statistics';

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $stats = LedgerStats::compute();

        if ($this->option('json')) {
            return $this->outputJson($stats);
        }

        return $this->outputFormatted($stats);
    }

    protected function outputFormatted(LedgerStats $stats): int
    {
        if ($stats->isEmpty()) {
            $this->line('Chronicle ledger is empty. No entries have been recorded yet.');

            return self::SUCCESS;
        }

        $this->line('Chronicle Ledger Stats');
        $this->line('======================');
        $this->newLine();

        $this->line('  Total entries:    '.number_format($stats->totalEntries()));
        $this->line('  Oldest entry:     '.($stats->oldestEntryAt()?->format('Y-m-d H:i:s T') ?? '-'));
        $this->line('  Newest entry:     '.($stats->newestEntryAt()?->format('Y-m-d H:i:s T') ?? '-'));
        $this->line('  Checkpoints:      '.number_format($stats->checkpointCount()));
        $this->newLine();

        $this->line('  Top Actions');
        $this->line('  -----------');
        $this->table(
            ['Action', 'Count'],
            array_map(
                fn (array $row): array => [$row['action'], number_format($row['count'])],
                $stats->topActions(),
            ),
        );
        $this->newLine();

        $this->line('  Activity (last 30 days)');
        $this->line('  -----------------------');
        foreach ($stats->dailyActivity() as $day) {
            $this->line(sprintf('  %-14s %s', $day['date'], number_format($day['count'])));
        }

        return self::SUCCESS;
    }

    /** @throws JsonException */
    protected function outputJson(LedgerStats $stats): int
    {
        $data = [
            'total_entries' => $stats->totalEntries(),
            'oldest_entry_at' => $stats->oldestEntryAt()?->toIso8601String(),
            'newest_entry_at' => $stats->newestEntryAt()?->toIso8601String(),
            'checkpoint_count' => $stats->checkpointCount(),
            'top_actions' => $stats->topActions(),
            'daily_activity' => $stats->dailyActivity(),
        ];

        $this->line(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
