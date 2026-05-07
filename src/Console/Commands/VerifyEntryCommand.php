<?php

namespace Chronicle\Console\Commands;

use Chronicle\Entry\Entry;
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
    protected $signature = 'chronicle:verify';

    protected $description = 'Verify the integrity of the Chronicle ledger';

    /**
     * @throws JsonException
     */
    public function handle(IntegrityVerifier $verifier): int
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
}
