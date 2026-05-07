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
