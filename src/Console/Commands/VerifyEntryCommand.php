<?php

namespace Chronicle\Console\Commands;

use Chronicle\Integrity\IntegrityVerifier;
use Illuminate\Console\Command;

/**
 * Verifies the integrity of the Chronicle ledger.
 *
 * This command checks:
 *  - payload hashes
 *  - chain hashes
 *  - checkpoint signatures
 */
class VerifyEntryCommand extends Command
{
    protected $signature = 'chronicle:verify';

    protected $description = 'Verify the integrity of the Chronicle ledger';

    public function handle(IntegrityVerifier $verifier): int
    {
        $this->info('Verifying Chronicle ledger...');

        $result = $verifier->verify();

        if ($result->hasFailed()) {
            $this->newLine();

            $this->error('Integrity violation detected.');

            $this->line('Type: '.$result->failureType());
            $this->line('Entry: '.$result->entryId());

            return self::FAILURE;
        }

        $this->info("Chronicle entries verified successfully ({$result->checked()} entries checked)");

        return self::SUCCESS;
    }
}
