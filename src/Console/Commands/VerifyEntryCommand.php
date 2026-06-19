<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\AnchorVerifier;
use Chronicle\Verification\CheckpointChainVerifier;
use Chronicle\Verification\EntryVerifier;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Chronicle\Verification\VerificationResult;
use Chronicle\Verification\VerificationRun;
use Chronicle\Verification\VerifiesCheckpointSignature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use JsonException;

/**
 * Verifies the integrity of the Chronicle ledger.
 *
 * Default: full from-genesis verification. Incremental modes trade scope for
 * speed and fall back to full verification (with a warning) when checkpoints
 * are not yet backfilled.
 */
final class VerifyEntryCommand extends Command
{
    use VerifiesCheckpointSignature;

    protected $signature = 'chronicle:verify
        {--entry= : ULID of a single entry to verify (omit to verify the full ledger)}
        {--checkpoints-only : Verify only the checkpoint chain (fast 0(checkpoints) attestation)}
        {--from-checkpoint= : Verify the segment seeded from this checkpoint}
        {--to-checkpoint= : With --from-checkpoint, the checkpoint that ends the segment (default: current head)}
        {--since-last-checkpoint : Trust the latest checkpoint and verify only the trail after it}
        {--anchors : Additionally verify external anchors for the checkpoints in scope}
        {--resume : Continue verification from the last recorded run (full verify if none)}';

    protected $description = 'Verify the integrity of the Chronicle ledger (full, single-entry, or incremental modes)';

    /**
     * @throws JsonException
     */
    public function handle(
        IntegrityVerifier $verifier,
        EntryVerifier $entryVerifier,
        CheckpointChainVerifier $chainVerifier,
        KeyRing $keyRing,
        AnchorVerifier $anchorVerifier,
    ): int {
        /** @var string|null $id */
        $id = $this->option('entry');

        if ($id !== null) {
            return $this->verifySingleEntry($id, $entryVerifier);
        }

        if ($this->option('resume')) {
            return $this->verifyResume($verifier);
        }

        /** @var string|null $fromCheckpoint */
        $fromCheckpoint = $this->option('from-checkpoint');

        $checkpointMode = $this->option('checkpoints-only')
            || $this->option('since-last-checkpoint')
            || $fromCheckpoint !== null;

        if ($checkpointMode && $this->checkpointsNotBackfilled()) {
            $this->warn('Checkpoints are not backfilled (run chronicle:checkpoints:backfill); falling back to full verification');

            return $this->verifyLedger($verifier);
        }

        $baseExit = match (true) {
            (bool) $this->option('checkpoints-only') => $this->reportIncremental($chainVerifier->verify(), 'checkpoints-only'),
            $fromCheckpoint !== null => $this->verifySegmentRange($verifier, $keyRing, $fromCheckpoint),
            (bool) $this->option('since-last-checkpoint') => $this->verifySinceLastCheckpoint($verifier),
            default => $this->verifyLedger($verifier),
        };

        if ($baseExit !== self::SUCCESS || ! $this->option('anchors')) {
            return $baseExit;
        }

        return $this->verifyAnchors($anchorVerifier);
    }

    protected function checkpointsNotBackfilled(): bool
    {
        return Checkpoint::query()->whereNull('head_id')->exists();
    }

    /**
     * @throws JsonException
     */
    protected function verifySegmentRange(IntegrityVerifier $verifier, KeyRing $keyRing, string $fromId): int
    {
        $from = Checkpoint::find($fromId);
        if ($from === null) {
            $this->error("From-checkpoint [$fromId] not found.");

            return self::FAILURE;
        }

        $fromFailure = $this->checkpointSignatureFailure($from, $keyRing);
        if ($fromFailure !== null) {
            $this->error("From-checkpoint signature invalid [$fromFailure].");

            return self::FAILURE;
        }

        $fromSequence = $this->headSequence($from);
        if ($fromSequence === null) {
            $this->error('From-checkpoint head entry not found (was the ledger pruned?).');

            return self::FAILURE;
        }

        /** @var string|null $toId */
        $toId = $this->option('to-checkpoint');

        if ($toId === null) {
            // From the checkpoint to the current head.
            return $this->reportIncremental($verifier->verifyFrom($from), 'from-checkpoint');
        }

        $to = Checkpoint::find($toId);
        if ($to === null) {
            $this->error("To-checkpoint [$toId] not found.");

            return self::FAILURE;
        }

        $toFailure = $this->checkpointSignatureFailure($to, $keyRing);
        if ($toFailure !== null) {
            $this->error("To-checkpoint signature invalid [$toFailure].");

            return self::FAILURE;
        }

        $toSequence = $this->headSequence($to);
        if ($toSequence === null) {
            $this->error('To-checkpoint head entry not found (was the ledger pruned?).');

            return self::FAILURE;
        }

        $result = $verifier->verifySegment(
            previousChain: $from->chain_hash,
            afterSequence: $fromSequence,
            throughSequence: $toSequence,
            expectedEndingChain: $to->chain_hash,
        );

        return $this->reportIncremental($result, 'segment');
    }

    /**
     * @throws JsonException
     */
    protected function verifySinceLastCheckpoint(IntegrityVerifier $verifier): int
    {
        $last = Checkpoint::query()
            ->orderByDesc('entry_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($last === null) {
            $this->warn('No checkpoints exist; falling back to full verification.');

            return $this->verifyLedger($verifier);
        }

        return $this->reportIncremental($verifier->verifyFrom($last), 'since-last-checkpoint');
    }

    protected function headSequence(Checkpoint $checkpoint): ?int
    {
        if ($checkpoint->head_id === null) {
            return null;
        }

        $sequence = Chronicle::newEntryQuery()->whereKey($checkpoint->head_id)->value('sequence');

        return is_numeric($sequence) ? (int) $sequence : null;
    }

    protected function reportIncremental(VerificationResult $result, string $mode): int
    {
        $this->info("Verifying Chronicle ledger ($mode)...");
        $this->newLine();

        if ($result->hasFailed()) {
            $this->error('Integrity violation detected.');
            $this->line('Type: '.$result->failureType());
            $this->line('Record: '.$result->entryId());

            return self::FAILURE;
        }

        $this->line('✓ Integrity verified');
        $this->line("Records checked: {$result->checked()}");
        $this->info('Ledger integrity OK');

        return self::SUCCESS;
    }

    /**
     * @throws JsonException
     */
    protected function verifyLedger(IntegrityVerifier $verifier): int
    {
        $this->info('Verifying Chronicle ledger...');
        $this->newLine();
        $this->line('Verifying entries');

        $total = Chronicle::newEntryQuery()->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $result = $verifier->verify(function (int $processed) use ($bar): void {
            $bar->setProgress($processed);
        });

        $bar->finish();
        $this->newLine();

        $this->newLine();
        if ($result->hasFailed()) {
            $this->error('Integrity violation detected.');

            $this->line('Type: '.$result->failureType());
            $this->line('Entry: '.$result->entryId());

            return self::FAILURE;
        }

        $this->line('✓ Chain integrity verified');
        $this->line('✓ Entry count validated');
        $this->line('✓ Dataset boundaries verified');
        $this->newLine();
        $this->line("Entries checked: {$result->checked()}");
        $this->info('Ledger integrity OK');

        $this->recordRun('full', $result->checked());

        return self::SUCCESS;
    }

    /**
     * @throws JsonException
     */
    protected function verifySingleEntry(string $id, EntryVerifier $entryVerifier): int
    {
        $this->line("Verifying entry <comment>$id</comment>...");
        $this->newLine();

        $result = $entryVerifier->verify($id);

        if ($result->failureCode() === VerificationFailure::NotFound->value) {
            $this->error("Entry [$id] not found.");

            return self::FAILURE;
        }

        $entry = $result->entry;
        if ($entry === null) {
            $this->error("Unexpected: result has no entry for code [{$result->failureCode()}].");

            return self::FAILURE;
        }

        $this->line("  Action:   <comment>$entry->action</comment>");
        $this->line("  Subject:  <comment>$entry->subject_type#$entry->subject_id</comment>");
        $this->line("  Actor:    <comment>$entry->actor_type#$entry->actor_id</comment>");
        $this->line("  Created:  <comment>{$entry->created_at->toDateTimeString()}</comment>");
        $this->newLine();

        if ($result->isValid()) {
            $this->line('  ✓ Payload hash OK');
            $this->line('  ✓ Chain hash OK');
            $this->newLine();
            $this->info('Entry integrity verified.');

            return self::SUCCESS;
        }

        $messages = [
            'payload_hash_mismatch' => 'Payload hash MISMATCH - entry data has been altered',
            'chain_hash_mismatch' => 'Chain hash MISMATCH - entry position has been manipulated',
        ];

        $code = $result->failureCode() ?? 'unknown';
        $label = $messages[$code] ?? 'Unknown integrity failure.';

        $this->line("  ✗ $label [$code]");
        $this->newLine();
        $this->error('Integrity violation detected.');

        return self::FAILURE;
    }

    /**
     * @throws JsonException
     */
    protected function verifyResume(IntegrityVerifier $verifier): int
    {
        if (! $this->resumeTableAvailable()) {
            $this->warn('Verification-run table is absent; running full verification.');

            return $this->verifyLedger($verifier);
        }

        $lastRun = VerificationRun::query()->orderByDesc('created_at')->orderByDesc('id')->first();

        $checkpoint = $lastRun?->last_checkpoint_id === null
            ? null
            : Checkpoint::find($lastRun->last_checkpoint_id);

        if ($checkpoint === null) {
            $this->warn('Resume found no previous run; running full verification.');

            return $this->verifyLedger($verifier);
        }

        $result = $verifier->verifyFrom($checkpoint);
        $exit = $this->reportIncremental($result, 'resume');

        if ($result->isValid()) {
            $this->recordRun('resume', $result->checked());
        }

        return $exit;
    }

    protected function resumeTableAvailable(): bool
    {
        $table = Config::string('chronicle.tables.verification_runs', 'chronicle_verification_runs');
        /** @var string|null $connection */
        $connection = Config::get('chronicle.connection');

        return Schema::connection($connection)->hasTable($table);
    }

    protected function recordRun(string $mode, int $verifiedCount): void
    {
        if (! $this->resumeTableAvailable()) {
            return;
        }

        /** @var string|null $lastCheckpointId */
        $lastCheckpointId = Checkpoint::query()
            ->orderByDesc('entry_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        VerificationRun::create([
            'mode' => $mode,
            'last_checkpoint_id' => $lastCheckpointId,
            'verified_count' => $verifiedCount,
            'status' => 'completed',
        ]);
    }

    protected function verifyAnchors(AnchorVerifier $anchorVerifier): int
    {
        $result = $anchorVerifier->verify($this->checkpointsInScope());

        if ($result->hasFailed()) {
            $this->error('Anchor verification failed.');
            $this->line('Type: '.$result->failureType());
            $this->line('Checkpoint: '.$result->entryId());

            return self::FAILURE;
        }

        $this->line("✓ Anchors verified: {$result->checked()}");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Checkpoint>
     */
    protected function checkpointsInScope(): Collection
    {
        /** @var string|null $fromId */
        $fromId = $this->option('from-checkpoint');
        /** @var string|null $toId */
        $toId = $this->option('to-checkpoint');

        $query = Checkpoint::query()->orderBy('entry_count');

        if ($this->option('since-last-checkpoint')) {
            return (clone $query)->reorder()->orderByDesc('entry_count')->limit(1)->get();
        }

        if ($fromId !== null) {
            $from = Checkpoint::find($fromId);
            if ($from !== null) {
                $query->where('entry_count', '>=', $from->entry_count);
            }
            if ($toId !== null) {
                $to = Checkpoint::find($toId);
                if ($to !== null) {
                    $query->where('entry_count', '<=', $to->entry_count);
                }
            }
        }

        /** @var Collection<int, Checkpoint> $all */
        $all = $query->get();

        return $all;
    }
}
