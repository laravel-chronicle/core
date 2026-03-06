<?php

use Chronicle\Contracts\SigningProvider;
use Chronicle\Export\ExportManifestBuilder;

it('builds an export manifest', function () {

    $signer = Mockery::mock(SigningProvider::class);
    $signer->shouldReceive('algorithm')->andReturn('ed25519');

    $builder = new ExportManifestBuilder($signer);

    $manifest = $builder->build(
        entryCount: 10,
        chainHead: 'abc123',
        datasetHash: 'xyz456',
        firstEntryId: '01HXYZFIRST',
        lastEntryId: '01HXYZLAST',
    );

    expect($manifest['entry_count'])->toBe(10)
        ->and($manifest['first_entry_id'])->toBe('01HXYZFIRST')
        ->and($manifest['last_entry_id'])->toBe('01HXYZLAST')
        ->and($manifest['chain_head'])->toBe('abc123')
        ->and($manifest['dataset_hash'])->toBe('xyz456');
});
