<?php

it('fails checkpoint command when ledger is empty', function () {
    $this->artisan('chronicle:checkpoint')
        ->expectsOutput('Creating Chronicle checkpoint...')
        ->expectsOutput('Checkpoint creation failed.')
        ->expectsOutputToContain('Cannot create checkpoint: ledger is empty.')
        ->assertExitCode(1);
});
