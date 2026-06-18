<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Carbon\Carbon;
use Chronicle\Entry\Entry;
use Chronicle\Lifecycle\LegalHold;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $query = $this->buildPruneQuery($cutoff, $force, $respectCheckpoints);

        if (! $force && $respectCheckpoints) {
            $query->whereNull('checkpoint_id');
        }

        if ($dryRun) {
            /** @var object{count: int, oldest: string|null, newest: string|null}|null $preview */
            $preview = $this->buildPruneQuery($cutoff, $force, $respectCheckpoints)
                ->selectRaw('COUNT(*) as count, MIN(created_at) as oldest, MAX(created_at) as newest')
                ->first();

            $count = $preview->count ?? 0;
            $oldest = $preview->oldest ?? null;
            $newest = $preview->newest ?? null;

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

        $this->warn('Pruning removes ledger history. After this, `IntegrityVerifier::verify()` (from genesis)');
        $this->warn('will no longer pass. Verify pruned ledgers from a boundary checkpoint via');
        $this->warn('`IntegrityVerifier::verifyFrom($checkpoint)`. Ensure a checkpoint anchors the prune boundary.');

        $deleted = 0;

        do {
            $ids = $this->buildPruneQuery($cutoff, $force, $respectCheckpoints)
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

            try {
                return Carbon::parse($before);
            } catch (Throwable) {
                $this->error("Invalid date format for --before: \"$before\". Expected Y-m-d.");

                return null;
            }
        }

        /** @var int|null $configured */
        $configured = config('chronicle.prune.default_retention_days');

        if ($configured !== null) {
            return Carbon::now()->subDays($configured);
        }

        return null;
    }

    /**
     * @return Builder<Entry>
     */
    protected function buildPruneQuery(Carbon $cutoff, bool $force, bool $respectCheckpoints): Builder
    {
        $query = Entry::query()->where('created_at', '<', $cutoff);

        if (! $force && $respectCheckpoints) {
            $query->whereNull('checkpoint_id');
        }

        $holdsTable = (new LegalHold)->getTable();
        $entriesTable = (new Entry)->getTable();

        $query->whereNotExists(function (QueryBuilder $sub) use ($holdsTable, $entriesTable) {
            $sub->select(DB::raw(1))
                ->from($holdsTable)
                ->whereColumn($holdsTable.'.subject_type', $entriesTable.'.subject_type')
                ->whereColumn($holdsTable.'.subject_id', $entriesTable.'.subject_id')
                ->whereNull($holdsTable.'.released_at');
        });

        return $query;
    }
}
