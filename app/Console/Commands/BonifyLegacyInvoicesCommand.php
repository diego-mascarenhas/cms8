<?php

namespace App\Console\Commands;

use App\Services\Finance\InvoiceLegacyBonificationService;
use App\Support\InvoiceLegacyBonificationLogWriter;
use Illuminate\Console\Command;

class BonifyLegacyInvoicesCommand extends Command
{
    protected $signature = 'invoices:bonify-legacy
                            {--team_id= : Limit to one team}
                            {--from-year= : Optional minimum invoice year}
                            {--until-year= : Optional maximum invoice year (omit for all years)}
                            {--log= : Optional log file path}
                            {--skip-fix-bonified : Do not zero balance on status 5/6 with balance > 0}
                            {--dry-run : Preview without writing}';

    protected $description = 'Bonify legacy manual invoices with outstanding balance and write a correction log';

    public function handle(
        InvoiceLegacyBonificationService $service,
        InvoiceLegacyBonificationLogWriter $logWriter,
    ): int {
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $fromYear = $this->filledOptionInt('from-year');
        $untilYear = $this->filledOptionInt('until-year');
        $dryRun = (bool) $this->option('dry-run');
        $fixBonifiedBalances = ! (bool) $this->option('skip-fix-bonified');
        $logPath = $this->option('log');

        $yearScope = $this->describeYearScope($fromYear, $untilYear);
        $this->info('Legacy bonification'.$yearScope.($dryRun ? ' (dry-run)' : '').'.');

        $report = $service->runCorrection(
            teamId: $teamId,
            untilYear: $untilYear,
            fromYear: $fromYear,
            dryRun: $dryRun,
            fixBonifiedBalances: $fixBonifiedBalances,
        );

        $writtenLogPath = $logWriter->write(
            $report,
            is_string($logPath) && $logPath !== '' ? $logPath : null,
        );

        $bonified = $report['summary']['bonified'];
        $balanceZeroed = $report['summary']['balance_zeroed'];

        $this->info(
            'Bonified: matched '.$bonified['matched']
            .' | updated '.$bonified['updated'],
        );

        if ($fixBonifiedBalances)
        {
            $this->info(
                'Balance zeroed (status 5/6): matched '.$balanceZeroed['matched']
                .' | updated '.$balanceZeroed['updated'],
            );
        }

        $this->info('Correction log: '.$writtenLogPath);

        if ($dryRun)
        {
            $this->comment('Dry run: no database changes were made.');
        }

        return self::SUCCESS;
    }

    private function filledOptionInt(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '')
        {
            return null;
        }

        return (int) $value;
    }

    private function describeYearScope(?int $fromYear, ?int $untilYear): string
    {
        if ($fromYear !== null && $untilYear !== null)
        {
            return " for years {$fromYear}-{$untilYear}";
        }

        if ($fromYear !== null)
        {
            return " from year {$fromYear}";
        }

        if ($untilYear !== null)
        {
            return " until year {$untilYear}";
        }

        return ' for all years';
    }
}
