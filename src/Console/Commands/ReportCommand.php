<?php

namespace Chronicle\Console\Commands;

use Carbon\Carbon;
use Chronicle\Reports\ComplianceReport;
use Illuminate\Console\Command;
use Throwable;

class ReportCommand extends Command
{
    protected $signature = 'chronicle:report
        {path : File path where the HTML report will be written}
        {--from= : Start of reporting period (Y-m-d or ISO 8601)}
        {--to= : End of reporting period (Y-m-d or ISO 8601)}';

    protected $description = 'Generate a signed compliance report for the Chronicle ledger';

    public function handle(ComplianceReport $reports): int
    {
        /** @var string $path */
        $path = $this->argument('path');

        /** @var string|null $fromOption */
        $fromOption = $this->option('from');

        /** @var string|null $toOption */
        $toOption = $this->option('to');

        $from = $fromOption !== null ? Carbon::parse($fromOption) : null;
        $to = $toOption !== null ? Carbon::parse($toOption) : null;

        $this->info('Generating Chronicle compliance report...');
        $this->newLine();

        try {
            $result = $reports->generate($path, $from, $to);
        } catch (Throwable $e) {
            $this->error('Report generation failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Report generated successfully.');
        $this->newLine();

        $this->line("Entries: <info>$result->entryCount</info>");
        $this->line('Chain head: <comment>'.($result->chainHead ?? '(empty ledger)').'</comment>');
        $this->line("Report hash: <comment>$result->reportHash</comment>");
        $this->line("Written to: <comment>$path</comment>");

        return self::SUCCESS;
    }
}
