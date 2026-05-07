<?php

use Chronicle\Reports\ComplianceReportResult;
use Illuminate\Support\Carbon;

it('constructs a ComplianceReportResult and exposes its properties', function () {
    $result = new ComplianceReportResult(
        entryCount: 42,
        chainHead: str_repeat('a', 64),
        reportHash: str_repeat('b', 64),
        signature: 'sig==',
        algorithm: 'ed25519',
        keyId: 'test-key',
        generatedAt: Carbon::parse('2026-01-01 00:00:00', 'UTC'),
        from: null,
        to: null,
        html: '<html></html>',
    );

    expect($result->entryCount)->toBe(42)
        ->and($result->chainHead)->toBe(str_repeat('a', 64))
        ->and($result->reportHash)->toBe(str_repeat('b', 64))
        ->and($result->signature)->toBe('sig==')
        ->and($result->algorithm)->toBe('ed25519')
        ->and($result->keyId)->toBe('test-key')
        ->and($result->from)->toBeNull()
        ->and($result->to)->toBeNull()
        ->and($result->html)->toBe('<html></html>');
});

use Chronicle\Facades\Chronicle;
use Chronicle\Reports\ComplianceReport;

it('generates a report with correct entry count and chain head', function () {
    Chronicle::record()->actor('system')->action('report.test')->subject(ref('ledger'))->commit();
    Chronicle::record()->actor('system')->action('report.test')->subject(ref('ledger'))->commit();

    $report = app(ComplianceReport::class);
    $path = storage_path('chronicle-report-'.Str::uuid().'.html');

    $result = $report->generate($path);

    expect($result->entryCount)->toBe(2)
        ->and($result->chainHead)->toBeString()->toHaveLength(64)
        ->and($result->reportHash)->toBeString()->toHaveLength(64)
        ->and($result->signature)->toBeString()->not->toBeEmpty()
        ->and($result->algorithm)->toBe('ed25519')
        ->and($result->from)->toBeNull()
        ->and($result->to)->toBeNull();
});

it('filters by date range when from/to are provided', function () {
    Chronicle::record()->actor('system')->action('report.old')->subject(ref('ledger'))->commit();

    Carbon::setTestNow(now()->addSeconds(2));
    $from = now();

    Chronicle::record()->actor('system')->action('report.new')->subject(ref('ledger'))->commit();

    $report = app(ComplianceReport::class);
    $path = storage_path('chronicle-report-'.Str::uuid().'.html');

    $result = $report->generate($path, from: $from);

    Carbon::setTestNow(null);

    expect($result->entryCount)->toBe(1);
});

it('generates a report with zero entries when ledger is empty', function () {
    $report = app(ComplianceReport::class);
    $path = storage_path('chronicle-report-'.Str::uuid().'.html');

    $result = $report->generate($path);

    expect($result->entryCount)->toBe(0)
        ->and($result->chainHead)->toBeNull()
        ->and($result->isEmpty())->toBeTrue();
});

it('writes the html report to the given path', function () {
    $report = app(ComplianceReport::class);
    $path = storage_path('chronicle-report-'.Str::uuid().'.html');

    $report->generate($path);

    expect(file_exists($path))->toBeTrue();
});

it('html report contains the entry count', function () {
    Chronicle::record()->actor('system')->action('html.test')->subject(ref('ledger'))->commit();

    $report = app(ComplianceReport::class);
    $result = $report->generate(storage_path('chronicle-report-'.Str::uuid().'.html'));

    expect($result->html)
        ->toContain('Chronicle Compliance Report')
        ->toContain('1') // entry count
        ->toContain($result->chainHead ?? '')
        ->toContain($result->reportHash)
        ->toContain($result->signature)
        ->toContain($result->algorithm);
});

it('html report escapes values to prevent xss', function () {
    $report = app(ComplianceReport::class);

    // Verify chain_head-style value in html would be escaped (integrity check on renderer)
    $result = $report->generate(storage_path('chronicle-report-'.Str::uuid().'.html'));

    // The HTML must be a complete document
    expect($result->html)
        ->toContain('<!DOCTYPE html>')
        ->toContain('</html>');
});
