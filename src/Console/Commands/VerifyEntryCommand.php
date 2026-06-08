<?php

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Entry\Entry;
use Chronicle\Signing\KeyRing;
use Chronicle\Verification\CheckpointChainVerifier;
use Chronicle\Verification\EntryVerifier;
use Chronicle\Verification\IntegrityVerifier;
use Chronicle\Verification\VerificationFailure;
use Chronicle\Verification\VerificationResult;
use Chronicle\Verification\VerifiesCheckpointSignature;
use Illuminate\Console\Command;
use JsonException;

/**
 * Verifies the integrity of the Chronicle ledger.
 *
 * Default: full from-genesis verification. Incremental modes trade scope for
 * speed and fall back to full verification (with a warning) when checkpoints
 * are not yet backfilled.
 */
class VerifyEntryCommand extends Command
{
    use VerifiesCheckpointSignature;

    protected $signature = 'chronicle:verify
        {--entry= : ULID of a single entry to verify (omit to verify the full ledger)}
        {--checkpoints-only : Verify only the checkpoint chain (fast 0(checkpoints) attestation)}
        {--from-checkpoint= : Verify the segment seeded from this checkpoint}
        {--to-checkpoint= : With --from-checkpoint, the checkpoint that ends the segment (default: current head)}
        {--since-last-checkpoint : Trust the latest checkpoint and verify only the trail after it}';

    protected $description = 'Verify the integrity of the Chronicle ledger (full, single-entry, or incremental modes)';

    /**
     * @throws JsonException
     */
    public function handle(
        IntegrityVerifier $verifier,
        EntryVerifier $entryVerifier,
        CheckpointChainVerifier $chainVerifier,
        KeyRing $keyRing,
    ): int {
        /** @var string|null $id */
        $id = $this->option('entry');

        if ($id !== null) {
            return $this->verifySingleEntry($id, $entryVerifier);
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

        if ($this->option('checkpoints-only')) {
            return $this->reportIncremental($chainVerifier->verify(), 'checkpoints-only');
        }

        if ($fromCheckpoint !== null) {
            return $this->verifySegmentRange($verifier, $keyRing, $fromCheckpoint);
        }

        if ($this->option('since-last-checkpoint')) {
            return $this->verifySinceLastCheckpoint($verifier);
        }

        return $this->verifyLedger($verifier);
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

        $sequence = Entry::query()->whereKey($checkpoint->head_id)->value('sequence');

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

        $total = Entry::query()->count();
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
            'payload_hash_mismatch' => 'Payload hash MISMATCH — entry data has been altered',
            'chain_hash_mismatch' => 'Chain hash MISMATCH — entry position has been manipulated',
        ];

        $code = $result->failureCode() ?? 'unknown';
        $label = $messages[$code] ?? 'Unknown integrity failure.';

        $this->line("  ✗ $label [$code]");
        $this->newLine();
        $this->error('Integrity violation detected.');

        return self::FAILURE;
    }
}
