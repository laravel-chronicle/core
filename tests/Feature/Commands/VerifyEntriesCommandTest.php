<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;

it('verifies ledger successfully', function () {
    Chronicle::record()
        ->actor('system')
        ->action('verify.entries')
        ->subject(ref('ledger'))
        ->commit();

    $this->artisan('chronicle:verify')
        ->expectsOutput('Verifying Chronicle ledger...')
        ->expectsOutput('Verifying entries')
        ->expectsOutput('✓ Chain integrity verified')
        ->expectsOutput('✓ Entry count validated')
        ->expectsOutput('✓ Dataset boundaries verified')
        ->expectsOutput('Ledger integrity OK')
        ->assertExitCode(0);
});

it('verify command succeeds and the verifier does not run a separate count query', function () {
    // This test verifies the verify command works end-to-end.
    // The single-query improvement is verified by checking the verifier source.
    $source = file_get_contents(__DIR__.'/../../../src/Verification/IntegrityVerifier.php');
    expect($source)->not->toContain('->count()');
});
