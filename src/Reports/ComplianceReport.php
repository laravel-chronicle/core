<?php

namespace Chronicle\Reports;

use Carbon\CarbonInterface;
use Chronicle\Contracts\SigningProvider;
use Chronicle\Entry\Entry;
use Illuminate\Support\Carbon;
use JsonException;
use RuntimeException;

class ComplianceReport
{
    public function __construct(
        protected SigningProvider $signer,
    ) {}

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

        $signature = $this->signer->sign($reportHash);

        $result = new ComplianceReportResult(
            entryCount: $entryCount,
            chainHead: $chainHead,
            reportHash: $reportHash,
            signature: $signature,
            algorithm: $this->signer->algorithm(),
            keyId: $this->signer->keyId(),
            generatedAt: $generatedAt,
            from: $from instanceof Carbon ? $from : ($from !== null ? Carbon::instance($from) : null),
            to: $to instanceof Carbon ? $to : ($to !== null ? Carbon::instance($to) : null),
            html: '',
        );

        $html = $this->renderHtml($result);

        $result = new ComplianceReportResult(
            entryCount: $result->entryCount,
            chainHead: $result->chainHead,
            reportHash: $result->reportHash,
            signature: $result->signature,
            algorithm: $result->algorithm,
            keyId: $result->keyId,
            generatedAt: $result->generatedAt,
            from: $result->from,
            to: $result->to,
            html: $html,
        );

        if (@file_put_contents($path, $html) === false) {
            throw new RuntimeException("Chronicle: failed to write compliance report to [$path].");
        }

        return $result;
    }

    /**
     * @return array{int, ?string, ?string, ?string}
     */
    protected function collectStats(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $query = Entry::query();

        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        $entryCount = (clone $query)->count();
        /** @var string $chainHead */
        $chainHead = (clone $query)->orderByDesc('id')->value('chain_hash');
        /** @var string $firstEntryId */
        $firstEntryId = (clone $query)->orderBy('id')->value('id');
        /** @var string $lastEntryId */
        $lastEntryId = (clone $query)->orderByDesc('id')->value('id');

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

    protected function renderHtml(ComplianceReportResult $result): string
    {
        return '';
    }
}
