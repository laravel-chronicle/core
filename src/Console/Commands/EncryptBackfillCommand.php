<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Encryption\BackfillReport;
use Chronicle\Encryption\EncryptBackfiller;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DELIBERATE, one-off re-baselining migration (decision D4). Encrypts the
 * configured PII fields of historical entries, which rewrites payload + columns
 * and therefore recomputes payload_hash and re-links chain_hash from the first
 * rewritten entry to the head, then creates a fresh signed checkpoint. This is
 * the only sanctioned ledger rewrite - it is never wired into normal recording.
 */
final class EncryptBackfillCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'chronicle:encrypt-backfill
        {--from= : Entry ULID to start the re-baseline from (default: genesis)}
        {--chunk=500 : Entries loaded per batch}
        {--dry-run : Report scope without writing}
        {--force : Skip confirmation; required to run in production}';

    protected $description = 'Encrypt historical entries and re-baseline the ledger (one-off migration)';

    /**
     * @throws Throwable
     */
    public function handle(EncryptBackfiller $backfiller, CheckpointCreator $checkpoints): int
    {
        if (Config::boolean('chronicle.encryption.enabled', false) !== true) {
            $this->error('chronicle.encryption.enabled is false. Enable encryption before backfilling.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        /** @var string|null $from */
        $from = $this->option('from');
        /** @var string $chunkOption */
        $chunkOption = $this->option('chunk');
        $chunk = max(1, (int) $chunkOption);

        $this->warn('================================================================');
        $this->warn(' chronicle:encrypt-backfill REWRITES ledger history.');
        $this->warn(' It recomputes payload_hash and re-links chain_hash to the head.');
        $this->warn(' TAKE A FULL DATABASE BACKUP BEFORE PROCEEDING.');
        $this->warn('================================================================');

        if (! $dryRun) {
            // Refuses in production unless --force (ConfirmableTrait).
            if (! $this->confirmToProceed()) {
                return self::FAILURE;
            }

            if (! $this->option('force')
                && ! $this->confirm('Have you taken a full backup and want to re-baseline the ledger now?')) {
                $this->warn('Aborted - no changes made.');

                return self::FAILURE;
            }
        }

        $conn = Config::get('chronicle.connection');
        $conn = is_string($conn) ? $conn : null;

        if ($dryRun) {
            $report = $backfiller->run($from, $chunk, true);
            $this->report($report);

            return self::SUCCESS;
        }

        /** @var BackfillReport $report */
        $report = DB::connection($conn)->transaction(function () use ($backfiller, $checkpoints, $from, $chunk) {
            $report = $backfiller->run($from, $chunk, false);

            if ($report->changed) {
                $checkpoint = $checkpoints->create();

                Log::warning('chronicle:encrypt-backfill re-baselined the ledger', [
                    'scanned' => $report->scanned,
                    'encrypted' => $report->encrypted,
                    'relinked' => $report->relinked,
                    'head_chain_hash' => $report->headChainHash,
                    'checkpoint_id' => $checkpoint->id,
                ]);
            }

            return $report;
        });

        $this->report($report);

        return self::SUCCESS;
    }

    protected function report(BackfillReport $report): int
    {
        $prefix = $report->dryRun ? '[dry run] ' : '';

        $this->info(sprintf(
            '%sScanned %d, encrypted %d, re-linked %d entries.',
            $prefix,
            $report->scanned,
            $report->encrypted,
            $report->relinked,
        ));

        if ($report->dryRun) {
            $this->info('[dry run] Nothing was written and no checkpoint was created.');
        } elseif ($report->changed) {
            $this->info(sprintf('Re-baseline complete. New head chain_hash: %s. A signed checkpoint was created.', $report->headChainHash));
        } else {
            $this->info('Ledger already encrypted - nothing rewritten, no checkpoint created.');
        }

        return self::SUCCESS;
    }
}
