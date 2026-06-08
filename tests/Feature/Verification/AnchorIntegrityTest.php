<?php

use Chronicle\Anchoring\CheckpointAnchorer;
use Chronicle\Anchoring\NullAnchor;
use Chronicle\Checkpoints\Checkpoint;
use Chronicle\Checkpoints\CheckpointCreator;
use Chronicle\Entry\Entry;
use Chronicle\Facades\Chronicle;
use Chronicle\Hashing\ChainHasher;
use Chronicle\Signing\KeyRing;
use Chronicle\Support\CanonicalPayloadSerializer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->useEloquentDriver();
    config([
        'chronicle.anchoring.enabled' => true,
        'chronicle.anchoring.providers' => ['null' => ['provider' => NullAnchor::class]],
    ]);
});

function anchoredSegment(int $base): Checkpoint
{
    foreach (range($base, $base + 1) as $i) {
        Chronicle::record()->actor(ref('a'))->action("a.$i")->subject(ref('s'))->commit();
    }
    $checkpoint = app(CheckpointCreator::class)->create();
    app(CheckpointAnchorer::class)->anchor($checkpoint, 'null');

    return $checkpoint;
}

it('passes --anchors when every checkpoint has a valid anchor', function () {
    anchoredSegment(1);
    anchoredSegment(3);

    $this->artisan('chronicle:verify', ['--checkpoints-only' => true, '--anchors' => true])
        ->assertSuccessful();
});

it('reports an unanchored checkpoint under --anchors', function () {
    anchoredSegment(1);
    // Second checkpoint with NO anchor.
    Chronicle::record()->actor(ref('a'))->action('a.x')->subject(ref('s'))->commit();
    app(CheckpointCreator::class)->create();

    $this->artisan('chronicle:verify', ['--checkpoints-only' => true, '--anchors' => true])
        ->assertFailed();
});

it('FULL COMPROMISE: rewrite + re-sign every checkpoint passes offline verify but FAILS --anchors', function () {
    anchoredSegment(1);
    anchoredSegment(3);

    // Attacker rewrites entry payloads, recomputes the whole chain, and re-signs
    // every checkpoint with a VALID key in the ring. Offline verify will pass.
    $serializer = app(CanonicalPayloadSerializer::class);
    $hasher = app(ChainHasher::class);
    $signer = app(KeyRing::class)->active();

    $previous = ChainHasher::GENESIS;
    foreach (Entry::query()->orderBy('sequence')->get() as $entry) {
        $payload = $entry->payload;
        $payload['action'] = 'tampered.'.$entry->sequence; // change the data
        $canonical = $serializer->serialize($payload);
        $payloadHash = hash('sha256', $canonical);
        $chainHash = $hasher->hash($previous, $payloadHash);

        DB::table('chronicle_entries')->where('id', $entry->id)->update([
            'action' => $payload['action'],
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'payload_hash' => $payloadHash,
            'chain_hash' => $chainHash,
        ]);
        $previous = $chainHash;
    }

    // Re-sign every checkpoint over its new head chain_hash with a valid key.
    foreach (Checkpoint::query()->orderBy('entry_count')->get() as $cp) {
        $newHeadChain = Entry::query()->whereKey($cp->head_id)->value('chain_hash');
        $payload = CheckpointCreator::signaturePayload(
            id: $cp->id,
            chainHash: $newHeadChain,
            algorithm: $signer->algorithm(),
            keyId: $signer->keyId(),
            createdAt: $cp->created_at->getTimestamp(),
        );
        DB::table('chronicle_checkpoints')->where('id', $cp->id)->update([
            'chain_hash' => $newHeadChain,
            'algorithm' => $signer->algorithm(),
            'key_id' => $signer->keyId(),
            'signature' => $signer->sign($payload),
        ]);
    }

    // Offline checkpoint-chain verify PASSES (everything re-signed consistently)...
    $this->artisan('chronicle:verify', ['--checkpoints-only' => true])->assertSuccessful();

    // ...but --anchors FAILS: the anchor proof binds the ORIGINAL digest, which
    // the rewrite changed, and the attacker could not forge the external anchor.
    $this->artisan('chronicle:verify', ['--checkpoints-only' => true, '--anchors' => true])
        ->assertFailed();
});
