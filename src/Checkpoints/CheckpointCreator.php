<?php

namespace Chronicle\Checkpoints;

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Entry\Entry;
use Chronicle\Jobs\AnchorCheckpointJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Class CheckpointCreator
 *
 * Responsible for creating cryptographic checkpoints
 * that anchor the Chronicle ledger.
 */
class CheckpointCreator
{
    protected SigningProvider $signer;

    protected AnchorManager $anchors;

    public function __construct(SigningProvider $signer, AnchorManager $anchors)
    {
        $this->signer = $signer;
        $this->anchors = $anchors;
    }

    /**
     * Create a checkpoint for the current ledger head.
     *
     * @throws Throwable
     */
    public function create(): Checkpoint
    {
        /** @var string|null $connection */
        $connection = config('chronicle.connection');

        $created = false;

        $checkpoint = DB::connection($connection)->transaction(function () use (&$created) {
            // Resolve the head by sequence (the canonical ledger order), not id.
            /** @var Entry|null $head */
            $head = Entry::query()
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first(['id', 'sequence', 'chain_hash']);

            if ($head === null) {
                throw new RuntimeException(
                    'Cannot create checkpoint: ledger is empty.'
                );
            }

            $chainHash = $head->chain_hash;

            $existing = Checkpoint::where('chain_hash', $chainHash)->first();

            if ($existing) {
                return $existing;
            }

            $id = (string) Str::ulid();
            $createdAt = now();

            $entryCount = Entry::query()
                ->where('sequence', '<=', $head->sequence)
                ->count();

            // value('id') returns mixed; it is only stored into the create()
            // attributes array (no cast), so no narrowing annotation is needed.
            $previousCheckpointId = Checkpoint::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('id');

            $signaturePayload = $this->signaturePayload(
                id: $id,
                chainHash: $chainHash,
                algorithm: $this->signer->algorithm(),
                keyId: $this->signer->keyId(),
                createdAt: $createdAt->getTimestamp(),
            );

            $signature = $this->signer->sign($signaturePayload);

            $checkpoint = Checkpoint::create([
                'id' => $id,
                'chain_hash' => $chainHash,
                'signature' => $signature,
                'algorithm' => $this->signer->algorithm(),
                'key_id' => $this->signer->keyId(),
                'head_id' => $head->id,
                'entry_count' => $entryCount,
                'previous_checkpoint_id' => $previousCheckpointId,
                'created_at' => $createdAt,
            ]);

            // Stamp checkpoint_id on covered, still-unanchored entries. This is a
            // query-builder UPDATE, so it bypasses the Entry model immutability
            // guard by design; checkpoint_id is NOT part of any hashed payload,
            // so no payload_hash/chain_hash is affected.
            Entry::query()
                ->whereNull('checkpoint_id')
                ->where('sequence', '<=', $head->sequence)
                ->update(['checkpoint_id' => $id]);

            $created = true;

            return $checkpoint;
        });

        if ($created) {
            $this->dispatchAnchoring($checkpoint);
        }

        return $checkpoint;
    }

    /**
     * The exact bytes a checkpoint signature covers. Shared with IntegrityVerifier
     * so signing and verification never drift.
     *
     * @throws JsonException
     */
    public static function signaturePayload(
        string $id,
        string $chainHash,
        string $algorithm,
        ?string $keyId,
        int $createdAt,
    ): string {
        return json_encode([
            'id' => $id,
            'chain_hash' => $chainHash,
            'algorithm' => $algorithm,
            'key_id' => $keyId,
            'created_at' => $createdAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected function dispatchAnchoring(Checkpoint $checkpoint): void
    {
        if (! $this->anchors->enabled()) {
            return;
        }

        /** @var string|null $queue */
        $queue = config('chronicle.anchoring.queue');

        foreach ($this->anchors->providerNames() as $providerName) {
            $job = new AnchorCheckpointJob($checkpoint->id, $providerName);

            if ($queue !== null) {
                $job->onQueue($queue);
            }

            dispatch($job);
        }
    }
}
