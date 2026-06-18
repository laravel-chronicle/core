<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\Lifecycle\LegalHold;
use Illuminate\Console\Command;

/**
 * Artisan command that places or releases a legal hold on a subject, blocking erasure and pruning.
 */
final class LegalHoldCommand extends Command
{
    protected $signature = 'chronicle:legal-hold
        {action : place or release}
        {type : Subject type}
        {id : Subject id}
        {--reason= : Reason for the hold}
        {--by= : Who placed the hold}';

    protected $description = 'Place or release a legal hold on a subject (blocks erasure/pruning)';

    public function handle(): int
    {
        /** @var string $action */
        $action = $this->argument('action');
        /** @var string $type */
        $type = $this->argument('type');
        /** @var string $id */
        $id = $this->argument('id');

        /** @var string|null $reason */
        $reason = $this->option('reason');
        /** @var string|null $by */
        $by = $this->option('by');

        return match ($action) {
            'place' => $this->place($type, $id, $reason, $by),
            'release' => $this->release($type, $id),
            default => $this->unknown($action),
        };
    }

    protected function place(string $type, string $id, ?string $reason, ?string $by): int
    {
        LegalHold::place($type, $id, $reason, $by);
        $this->info("Legal hold placed on [$type:$id].");

        return self::SUCCESS;
    }

    protected function release(string $type, string $id): int
    {
        $released = LegalHold::release($type, $id);
        $this->info($released > 0
            ? "Legal hold released on [$type:$id]."
            : "No active legal hold on [$type:$id].");

        return self::SUCCESS;
    }

    protected function unknown(string $action): int
    {
        $this->error("Unknown action \"$action\". Use \"place\" or \"release\".");

        return self::FAILURE;
    }
}
