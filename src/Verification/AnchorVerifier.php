<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Anchoring\AnchorManager;
use Chronicle\Anchoring\AnchorReceipt;
use Chronicle\Checkpoints\Checkpoint;
use Illuminate\Database\Eloquent\Collection;

/**
 * Verifies that each in-scope checkpoint carries at least one valid external
 * anchor. An unanchored or invalid-anchor checkpoint fails - it is never
 * silently passed.
 */
readonly class AnchorVerifier
{
    public function __construct(
        protected AnchorManager $manager,
    ) {
        //
    }

    /**
     * @param  Collection<int, Checkpoint>  $checkpoints
     */
    public function verify(Collection $checkpoints): VerificationResult
    {
        $result = new VerificationResult;
        $count = 0;

        foreach ($checkpoints as $checkpoint) {
            if (! $this->checkpointHasValidAnchor($checkpoint)) {
                $result->fail(VerificationFailure::AnchorInvalid->value, $checkpoint->id);

                return $result;
            }

            $count++;
        }

        $result->success($count);

        return $result;
    }

    public function checkpointHasValidAnchor(Checkpoint $checkpoint): bool
    {
        $anchors = $checkpoint->anchors()->where('status', 'anchored')->get();

        if ($anchors->isEmpty()) {
            return false;
        }

        foreach ($anchors as $anchor) {
            $provider = $this->manager->provider($anchor->provider);

            $valid = $provider->verify(
                $checkpoint,
                new AnchorReceipt($anchor->provider, $anchor->reference, $anchor->proof, $anchor->anchored_at ?? now()->toImmutable()),
            );

            if (! $valid) {
                return false;
            }
        }

        return true;
    }
}
