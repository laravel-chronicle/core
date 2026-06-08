<?php

namespace Chronicle\Console\Commands;

use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Entry\Entry;
use Illuminate\Console\Command;

/**
 * Backfills the v1.11 range columns (head_id, entry_count, previous_checkpoint_id)
 * and the entries.checkpoint_id coverage for checkpoints created before v1.11.
 * Everything is computable from existing data. Idempotent; supports --dry-rune.
 */
class CheckpointsBackfillCommand extends Command
{
    protected $signature = 'chronicle:checkpoints:backfill
        {--chunk=1000 : Number of entries to stamp per update batch}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill range columns and checkpoint_id coverage for pre-1.11 checkpoints';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // PHPStan level 10 rejects (int) on mixed; annotate the option as string
        // first (the established pattern - see PruneCommand::resolveCutOff()).
        /** @var string $chunkOption */
        $chunkOption = $this->option('chunk');
        $chunk = max(1, (int) $chunkOption);

        $previousId = null;
        $updatedCheckpoints = 0;
        $stampedEntries = 0;

        // Fetch checkpoints eagerly (they are few relative to entries): the loop
        // body issues UPDATEs to the same connection, which is unsafe to do while
        // an unbuffered cursor over the same table is open on some drivers.
        $checkpoints = Checkpoint::query()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($checkpoints as $checkpoint) {
            /** @var Entry|null $head */
            $head = Entry::query()
                ->where('chain_hash', $checkpoint->chain_hash)
                ->first(['id', 'sequence']);

            if ($head === null) {
                $this->warn("Checkpoint $checkpoint->id: no entry matches its chain_hash; skipping.");
                $previousId = $checkpoint->id;

                continue;
            }

            $entryCount = Entry::query()
                ->where('sequence', '<=', $head->sequence)
                ->count();

            $needsColumns = $checkpoint->head_id !== $head->id
                || $checkpoint->entry_count !== $entryCount
                || $checkpoint->previous_checkpoint_id !== $previousId;

            if ($needsColumns) {
                if ($dryRun) {
                    $this->line("[dry run] Would set range columns on checkpoint $checkpoint->id.");
                } else {
                    // Range columns are not part of the signed payload; write them
                    // directly via the query builder to bypass immutability.
                    Checkpoint::query()->whereKey($checkpoint->id)->update([
                        'head_id' => $head->id,
                        'entry_count' => $entryCount,
                        'previous_checkpoint_id' => $previousId,
                    ]);
                }
                $updatedCheckpoints++;
            }

            // Stamp checkpoint_id on this segment's still-unstamped entries, chunked.
            $segment = Entry::query()
                ->whereNull('checkpoint_id')
                ->where('sequence', '<=', $head->sequence);

            $segmentCount = (clone $segment)->count();

            if ($segmentCount > 0) {
                if ($dryRun) {
                    $this->line("[dry run] Would stamp $segmentCount entries for checkpoint $checkpoint->id.");
                } else {
                    do {
                        $ids = (clone $segment)->limit($chunk)->pluck('id');

                        if ($ids->isEmpty()) {
                            break;
                        }

                        Entry::query()->whereIn('id', $ids)
                            ->update(['checkpoint_id' => $checkpoint->id]);
                    } while ($ids->count() === $chunk);
                }
                $stampedEntries += $segmentCount;
            }

            $previousId = $checkpoint->id;
        }

        $prefix = $dryRun ? '[dry run] ' : '';
        $this->info(sprintf(
            '%sBackfill complete: %d checkpoint(s) updated, %d entry(ies) stamped.',
            $prefix,
            $updatedCheckpoints,
            $stampedEntries,
        ));

        return self::SUCCESS;
    }
}
