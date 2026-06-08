<?php

use Chronicle\Verification\VerificationFailure;

it('defines the checkpoint-chain and segment failure reasons', function () {
    expect(VerificationFailure::CheckpointChainBroken->value)->toBe('checkpoint_chain_broken')
        ->and(VerificationFailure::CheckpointHeadMismatch->value)->toBe('checkpoint_head_mismatch')
        ->and(VerificationFailure::SegmentDiscontinuous->value)->toBe('segment_discontinuous');
});
