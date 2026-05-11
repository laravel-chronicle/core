<?php

namespace Chronicle\Console\Commands;

use Carbon\Carbon;
use Chronicle\Entry\Entry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneCommand extends Command
{
    protected $signature = 'chronicle:prune
        {--older-than= : Delete entries older than N days}
        {--before= : Delete entries created before this date (Y-m-d)}
        {--dry-run : Preview what would be deleted without deleting}
        {--force : Delete entries even if they are anchored by a checkpoint}';

    protected $description = 'Prune Chronicle audit entries by retention policy';

    public function handle(): int
    {
        $cutoff = $this->resolveCutoff();

        if ($cutoff === null) {
            $this->error('No retention target specified. Pass --older-than=N or --before=YYYY-MM-DD, or set chronicle.prune.default_retention_days in config.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        /** @var bool $respectCheckpoints */
        $respectCheckpoints = config('chronicle.prune.respect_checkpoints', true);

        if (! $force && $respectCheckpoints) {
            $anchored = Entry::query()
                ->where('created_at', '<', $cutoff)
                ->whereNotNull('checkpoint_id')
                ->count();

            if ($anchored > 0) {
                $this->error(
                    sprintf(
                        '%d entries in the prune range are anchored by a checkpoint. Use --force to override.',
                        $anchored
                    )
                );

                return self::FAILURE;
            }
        }

        $query = Entry::query()->where('created_at', '<', $cutoff);

        if (! $force && $respectCheckpoints) {
            $query->whereNull('checkpoint_id');
        }

        $count = $query->count();

        if ($dryRun) {
            /** @var string|null $oldest */
            $oldest = Entry::query()->where('created_at', '<', $cutoff)->min('created_at');
            /** @var string|null $newest */
            $newest = Entry::query()->where('created_at', '<', $cutoff)->max('created_at');

            $this->info(sprintf(
                '[dry run] Would prune %d entries (oldest: %s, newest: %s).',
                $count,
                $oldest ?? 'n/a',
                $newest ?? 'n/a',
            ));

            return self::SUCCESS;
        }

        /** @var string $table */
        $table = config('chronicle.tables.entries', 'chronicle_entries');

        /** @var string|null $conn */
        $conn = config('chronicle.connection');

        $deleted = 0;

        do {
            $ids = Entry::query()
                ->where('created_at', '<', $cutoff)
                ->when(! $force && $respectCheckpoints, fn ($q) => $q->whereNull('checkpoint_id'))
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            DB::connection($conn)->table($table)->whereIn('id', $ids)->delete();
            $deleted += $ids->count();
        } while ($ids->count() === 1000);

        $this->info(sprintf('Pruned %d entries older than %s.', $deleted, $cutoff->toDateString()));

        return self::SUCCESS;
    }

    protected function resolveCutoff(): ?Carbon
    {
        if ($this->option('older-than') !== null) {
            /** @var string $days */
            $days = $this->option('older-than');

            return Carbon::now()->subDays((int) $days);
        }

        if ($this->option('before') !== null) {
            /** @var string $before */
            $before = $this->option('before');

            return Carbon::parse($before);
        }

        /** @var int|null $configured */
        $configured = config('chronicle.prune.default_retention_days');

        if ($configured !== null) {
            return Carbon::now()->subDays($configured);
        }

        return null;
    }
}
