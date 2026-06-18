<?php

declare(strict_types=1);

namespace Chronicle\Console\Commands;

use Chronicle\ChronicleManager;
use Chronicle\Lifecycle\LegalHold;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan command that crypto-shreds a subject (GDPR Art. 17) and records a PII-free erasure proof.
 */
final class SubjectEraseCommand extends Command
{
    protected $signature = 'chronicle:subject:erase
        {type : Subject type}
        {id : Subject id}
        {--reason= : Reason recorded in the erasure proof}
        {--force : Override an active legal hold (audited)}';

    protected $description = 'Crypto-shred a subject (GDPR Art. 17) and record a PII-free proof';

    /**
     * @throws Throwable
     */
    public function handle(ChronicleManager $chronicle): int
    {
        /** @var string $type */
        $type = $this->argument('type');
        /** @var string $id */
        $id = $this->argument('id');
        /** @var string|null $reason */
        $reason = $this->option('reason');
        $force = (bool) $this->option('force');

        $held = LegalHold::isHeld($type, $id);

        if ($held && ! $force) {
            $this->error("Subject [$type:$id] is under a legal hold. Use --force to override (the override is audited).");

            return self::FAILURE;
        }

        $requester = (get_current_user() ?: 'cli');

        $erased = $chronicle->eraseSubject($type, $id, $requester, $reason, $held);

        $this->info($erased
            ? "Subject [$type:$id] erased."
            : "Subject [$type:$id] was already erased (no-op).");

        return self::SUCCESS;
    }
}
