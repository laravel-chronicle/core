<?php

use Chronicle\Facades\Chronicle;

it('verifies ledger successfully', function () {
    Chronicle::record()
        ->actor('system')
        ->action('test')
        ->subject('ledger')
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
