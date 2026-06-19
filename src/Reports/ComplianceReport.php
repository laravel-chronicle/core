<?php

declare(strict_types=1);

namespace Chronicle\Reports;

use Carbon\CarbonInterface;
use Chronicle\Entry\Entry;
use Chronicle\Exceptions\UnknownSigningKeyException;
use Chronicle\Facades\Chronicle;
use Chronicle\Signing\KeyRing;
use Illuminate\Support\Carbon;
use JsonException;
use RuntimeException;

/**
 * Generates a signed compliance report summarizing ledger integrity and coverage.
 */
class ComplianceReport
{
    public function __construct(
        protected KeyRing $keyRing,
    ) {
        //
    }

    public function generate(
        string $path,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): ComplianceReportResult {
        $generatedAt = Carbon::now('UTC');

        [$entryCount, $chainHead, $firstEntryId, $lastEntryId] = $this->collectStats($from, $to);

        $reportHash = $this->hashReportData(
            $generatedAt, $entryCount, $firstEntryId, $lastEntryId, $chainHead, $from, $to
        );

        $signer = $this->keyRing->active();
        $signature = $signer->sign($reportHash);
        $algorithm = $signer->algorithm();
        $keyId = $signer->keyId();

        $fromCarbon = $from instanceof Carbon ? $from : ($from !== null ? Carbon::instance($from) : null);
        $toCarbon = $to instanceof Carbon ? $to : ($to !== null ? Carbon::instance($to) : null);

        $html = $this->renderHtml(
            entryCount: $entryCount,
            chainHead: $chainHead,
            reportHash: $reportHash,
            signature: $signature,
            algorithm: $algorithm,
            keyId: $keyId,
            generatedAt: $generatedAt,
            from: $fromCarbon,
            to: $toCarbon,
        );

        $result = new ComplianceReportResult(
            entryCount: $entryCount,
            chainHead: $chainHead,
            reportHash: $reportHash,
            signature: $signature,
            algorithm: $algorithm,
            keyId: $keyId,
            generatedAt: $generatedAt,
            from: $fromCarbon,
            to: $toCarbon,
            html: $html,
        );

        if (file_put_contents($path, $html) === false) {
            throw new RuntimeException("Chronicle: failed to write compliance report to [$path].");
        }

        return $result;
    }

    public function verify(
        string $reportHash,
        string $signature,
        string $algorithm,
        ?string $keyId
    ): bool {
        try {
            $provider = $this->keyRing->resolve($algorithm, $keyId);
        } catch (UnknownSigningKeyException) {
            return false;
        }

        return $provider->verify($reportHash, $signature);
    }

    /**
     * @return array{int, ?string, ?string, ?string}
     */
    protected function collectStats(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $query = Chronicle::newEntryQuery();

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        $entryCount = (clone $query)->count();
        /** @var string|null $chainHead */
        $chainHead = (clone $query)->orderByDesc('id')->value('chain_hash');

        /** @var object{first_id: string|null, last_id: string|null}|null $bounds */
        $bounds = (clone $query)
            ->selectRaw('MIN(id) as first_id, MAX(id) as last_id')
            ->first();

        $firstEntryId = $bounds?->first_id;
        $lastEntryId = $bounds?->last_id;

        return [$entryCount, $chainHead, $firstEntryId, $lastEntryId];
    }

    protected function hashReportData(
        Carbon $generatedAt,
        int $entryCount,
        ?string $firstEntryId,
        ?string $lastEntryId,
        ?string $chainHead,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): string {
        try {
            $canonical = json_encode([
                'generated_at' => $generatedAt->toIso8601String(),
                'entry_count' => $entryCount,
                'first_entry_id' => $firstEntryId,
                'last_entry_id' => $lastEntryId,
                'chain_head' => $chainHead,
                'from' => $from?->toIso8601String(),
                'to' => $to?->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Chronicle: failed to encode compliance report data.', 0, $e);
        }

        return hash('sha256', $canonical);
    }

    protected function renderHtml(
        int $entryCount,
        ?string $chainHead,
        string $reportHash,
        string $signature,
        string $algorithm,
        ?string $keyId,
        Carbon $generatedAt,
        ?Carbon $from,
        ?Carbon $to,
    ): string {
        $e = fn (int|string|null $value): string => htmlspecialchars((string) ($value ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $period = $from !== null || $to !== null
            ? $e($from?->toDateString() ?? '∞').' – '.$e($to?->toDateString() ?? '∞')
            : 'All entries';

        $chainHeadHtml = $chainHead !== null ? $e($chainHead) : '<em>empty ledger</em>';

        return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Chronicle Compliance Report</title>
      <style>
        body { font-family: system-ui, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; color: #1a1a1a; }
        h1 { font-size: 1.4rem; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th { text-align: left; width: 200px; padding: 10px 12px; background: #f5f5f5; font-weight: 600; vertical-align: top; }
        td { padding: 10px 12px; word-break: break-all; font-family: monospace; font-size: 0.85rem; }
        tr { border-bottom: 1px solid #e0e0e0; }
        .label { font-family: system-ui, sans-serif; font-size: 0.9rem; }
        .sig-block { margin-top: 32px; padding: 16px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-size: 0.75rem; color: #555; }
        .sig-block strong { color: #1a1a1a; display: block; margin-bottom: 8px; }
        @media print { body { margin: 20px; } }
      </style>
    </head>
    <body>
      <h1>Chronicle Compliance Report</h1>
      <table>
        <tr><th class="label">Generated</th><td>{$e($generatedAt->toIso8601String())}</td></tr>
        <tr><th class="label">Period</th><td>$period</td></tr>
        <tr><th class="label">Entry count</th><td>{$e($entryCount)}</td></tr>
        <tr><th class="label">Chain head</th><td>$chainHeadHtml</td></tr>
        <tr><th class="label">Report hash (SHA-256)</th><td>{$e($reportHash)}</td></tr>
      </table>
      <div class="sig-block">
        <strong>Signature block</strong>
        <div><b>Algorithm:</b> {$e($algorithm)}</div>
        <div><b>Key ID:</b> {$e($keyId)}</div>
        <div style="margin-top:8px;word-break:break-all;"><b>Signature:</b> {$e($signature)}</div>
      </div>
    </body>
    </html>
    HTML;
    }
}
