<?php

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoint\CheckpointCreator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Class CreateCheckpointCommand
 *
 * Creates a cryptographic checkpoint anchoring the current
 * Chronicle ledger head.
 */
class CreateCheckpointCommand extends Command
{
    /**
     * Command signature.
     */
    protected $signature = 'chronicle:checkpoint';

    /**
     * Command description.
     */
    protected $description = 'Create a cryptographic checkpoint for the Chronicle ledger';

    /**
     * Execute the command.
     */
    public function handle(CheckpointCreator $creator): int
    {
        $this->info('Creating Chronicle checkpoint...');

        try {
            $checkpoint = $creator->create();

            $this->info('Checkpoint created successfully.');

            $this->line('ID: '.$checkpoint->id);
            $this->line('Chain Hash: '.$checkpoint->chain_hash);
            $this->line('Algorithm: '.$checkpoint->algorithm);
            $this->line('Key ID: '.$checkpoint->key_id);
            $this->line('Created At: '.$checkpoint->created_at);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Checkpoint creation failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
