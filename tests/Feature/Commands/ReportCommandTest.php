<?php

declare(strict_types=1);

use Chronicle\Facades\Chronicle;
use Chronicle\Reports\ComplianceReport;
use Illuminate\Support\Str;

it('generates a report via artisan command', function () {
    Chronicle::record()->actor('system')->action('cmd.report')->subject(ref('ledger'))->commit();

    $path = storage_path('chronicle-cmd-report-'.Str::uuid().'.html');

    $this->artisan('chronicle:report', ['path' => $path])
        ->assertExitCode(0);

    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('Chronicle Compliance Report');
});

it('outputs entry count and report hash after generation', function () {
    $path = storage_path('chronicle-cmd-report-'.Str::uuid().'.html');

    $this->artisan('chronicle:report', ['path' => $path])
        ->expectsOutputToContain('Entries:')
        ->expectsOutputToContain('Report hash:')
        ->assertExitCode(0);
});

it('handles ComplianceReport exceptions at command level', function () {
    $mock = Mockery::mock(ComplianceReport::class);
    $mock->shouldReceive('generate')->once()->andThrow(new RuntimeException('disk full'));
    app()->instance(ComplianceReport::class, $mock);

    $this->artisan('chronicle:report', ['path' => '/dev/null/impossible.html'])
        ->expectsOutput('Report generation failed.')
        ->expectsOutputToContain('disk full')
        ->assertExitCode(1);
});

it('accepts --from and --to date range options', function () {
    $path = storage_path('chronicle-cmd-report-'.Str::uuid().'.html');

    $this->artisan('chronicle:report', [
        'path' => $path,
        '--from' => '2026-01-01',
        '--to' => '2026-12-31',
    ])->assertExitCode(0);
});
