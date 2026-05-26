<?php

namespace Chronicle\Console\Commands;

use Chronicle\Entry\Entry;
use Chronicle\Verification\EntryVerifier;
use Chronicle\Verification\IntegrityVerifier;
use Illuminate\Console\Command;
use JsonException;

/**
 * Verifies the integrity of the Chronicle ledger.
 *
 * This command checks:
 *  - Payload hashes
 *  - Chain hashes
 *  - Checkpoint signatures
 */
class VerifyEntryCommand extends Command
{
    protected $signature = 'chronicle:verify
        {--entry= : ULID of a single entry to verify (omit to verify the full ledger)}';

    protected $description = 'Verify the integrity of the Chronicle ledger (or a single entry with --entry=<id>)';

    /**
     * @throws JsonException
     */
    public function handle(IntegrityVerifier $verifier, EntryVerifier $entryVerifier): int
    {
        /** @var string|null $id */
        $id = $this->option('entry');

        if ($id !== null) {
            return $this->verifySingleEntry($id, $entryVerifier);
        }

        return $this->verifyLedger($verifier);
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

        if ($result->failureCode() === 'not_found') {
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
        $label = $messages[$code] ?? $code;

        $this->line("  ✗ $label [$code]");
        $this->newLine();
        $this->error('Integrity violation detected.');

        return self::FAILURE;
    }
}
