<?php

namespace App\Services\Billing;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\PaymentSync;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MercadoPagoSettlementPayerEnricher
{
    /**
     * Mercado Pago rejects creation above ~60 days; calendar months stay under that limit.
     */
    private const REPORT_TIMEZONE = 'America/Argentina/Buenos_Aires';

    private const CONFIG_COLUMNS = [
        'SOURCE_ID',
        'TRANSACTION_DATE',
        'TRANSACTION_AMOUNT',
        'PAYMENT_METHOD_TYPE',
        'PAYER_NAME',
        'PAYER_ID_TYPE',
        'PAYER_ID_NUMBER',
        'PAY_BANK_TRANSFER_ID',
        'EXTERNAL_REFERENCE',
    ];

    /**
     * Pull Account money report rows, persist statement lines, and enrich payment_syncs by SOURCE_ID.
     *
     * @return array{enriched: int, report_rows: int, unmatched: int, skipped: int, chunks: int, statement_lines: int, months_skipped: int}
     */
    public function enrichTeam(
        Team $team,
        Carbon $beginDate,
        Carbon $endDate,
        bool $dryRun = false,
        int $pollSeconds = 90,
        bool $reuseExisting = false,
        bool $force = false,
    ): array {
        $token = trim((string) $team->getSetting('mercadopago_access_token'));
        if ($token === '')
        {
            throw new RuntimeException('Missing mercadopago_access_token for team '.$team->id);
        }

        $client = $this->http($token);
        $this->ensureReportConfig($client);

        if ($reuseExisting)
        {
            return $this->enrichFromExistingReports($client, $team, $beginDate, $endDate, $dryRun, $force);
        }

        $enriched = 0;
        $unmatched = 0;
        $skipped = 0;
        $reportRows = 0;
        $chunks = 0;
        $statementLines = 0;
        $monthsSkipped = 0;

        foreach ($this->dateChunks($beginDate, $endDate) as $chunk)
        {
            $chunks++;
            [$chunkBegin, $chunkEnd] = $chunk;

            if ($this->shouldSkipProcessedMonth($team, $chunkBegin, $force))
            {
                $monthsSkipped++;

                Log::info('Mercado Pago settlement month already processed; skipping', [
                    'team_id' => $team->id,
                    'begin' => $chunkBegin->toDateString(),
                    'end' => $chunkEnd->toDateString(),
                ]);

                continue;
            }

            $fileName = $this->findBestExistingReportForMonth($client, $chunkBegin, $chunkEnd);

            if ($fileName === null)
            {
                try
                {
                    $fileName = $this->createAndWaitForReport($client, $chunkBegin, $chunkEnd, $pollSeconds);
                } catch (RuntimeException $exception)
                {
                    if (! str_contains($exception->getMessage(), 'HTTP 429'))
                    {
                        throw $exception;
                    }

                    $fileName = $this->findExistingReportFileName($client, $chunkBegin, $chunkEnd);
                    if ($fileName === null)
                    {
                        throw new RuntimeException(
                            'Unable to create settlement report (HTTP 429 quota) and no existing report covers '
                            .$chunkBegin->toDateString().' → '.$chunkEnd->toDateString().'. '
                            .'Wait for Mercado Pago to free report slots, or pass --reuse-existing for months that already have files.',
                            0,
                            $exception,
                        );
                    }

                    Log::warning('Mercado Pago settlement report quota hit; reusing existing file', [
                        'team_id' => $team->id,
                        'file_name' => $fileName,
                        'begin' => $chunkBegin->toDateString(),
                        'end' => $chunkEnd->toDateString(),
                    ]);
                }
            } else
            {
                Log::info('Mercado Pago settlement reusing existing report for month', [
                    'team_id' => $team->id,
                    'file_name' => $fileName,
                    'begin' => $chunkBegin->toDateString(),
                    'end' => $chunkEnd->toDateString(),
                ]);
            }

            $csv = $this->downloadReport($client, $fileName);
            $rowsBySourceId = $this->parseCsvBySourceId($csv);
            $reportRows += count($rowsBySourceId);

            if (! $dryRun)
            {
                $statementLines += $this->persistStatementLines($team, $rowsBySourceId, $fileName);
            }

            [$chunkEnriched, $chunkUnmatched, $chunkSkipped] = $this->applyRowsToSyncs(
                $team,
                $rowsBySourceId,
                $dryRun,
            );
            $enriched += $chunkEnriched;
            $unmatched += $chunkUnmatched;
            $skipped += $chunkSkipped;
        }

        return [
            'enriched' => $enriched,
            'report_rows' => $reportRows,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'chunks' => $chunks,
            'statement_lines' => $statementLines,
            'months_skipped' => $monthsSkipped,
        ];
    }

    /**
     * Download and apply already-generated settlement reports that overlap the window.
     * Does not create new reports (safe when MP returns "Max number of reports achieved").
     *
     * @return array{enriched: int, report_rows: int, unmatched: int, skipped: int, chunks: int, statement_lines: int, months_skipped: int}
     */
    public function enrichFromExistingReports(
        PendingRequest $client,
        Team $team,
        Carbon $beginDate,
        Carbon $endDate,
        bool $dryRun = false,
        bool $force = false,
    ): array {
        $enriched = 0;
        $unmatched = 0;
        $skipped = 0;
        $reportRows = 0;
        $statementLines = 0;
        $monthsSkipped = 0;
        $chunks = 0;

        foreach ($this->dateChunks($beginDate, $endDate) as $chunk)
        {
            $chunks++;
            [$chunkBegin, $chunkEnd] = $chunk;

            if ($this->shouldSkipProcessedMonth($team, $chunkBegin, $force))
            {
                $monthsSkipped++;

                continue;
            }

            $fileName = $this->findBestExistingReportForMonth($client, $chunkBegin, $chunkEnd);
            if ($fileName === null)
            {
                Log::warning('Mercado Pago settlement reuse-existing: no report for month', [
                    'team_id' => $team->id,
                    'begin' => $chunkBegin->toDateString(),
                    'end' => $chunkEnd->toDateString(),
                ]);

                continue;
            }

            $csv = $this->downloadReport($client, $fileName);
            $rowsBySourceId = $this->parseCsvBySourceId($csv);
            $reportRows += count($rowsBySourceId);

            if (! $dryRun)
            {
                $statementLines += $this->persistStatementLines($team, $rowsBySourceId, $fileName);
            }

            [$fileEnriched, $fileUnmatched, $fileSkipped] = $this->applyRowsToSyncs(
                $team,
                $rowsBySourceId,
                $dryRun,
            );
            $enriched += $fileEnriched;
            $unmatched += $fileUnmatched;
            $skipped += $fileSkipped;
        }

        if ($reportRows === 0 && $monthsSkipped === 0)
        {
            throw new RuntimeException(
                'No existing settlement reports cover '.$beginDate->toDateString().' → '.$endDate->toDateString().'.',
            );
        }

        return [
            'enriched' => $enriched,
            'report_rows' => $reportRows,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'chunks' => $chunks,
            'statement_lines' => $statementLines,
            'months_skipped' => $monthsSkipped,
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $rowsBySourceId
     * @return array{0: int, 1: int, 2: int} enriched, unmatched, skipped
     */
    private function applyRowsToSyncs(Team $team, array $rowsBySourceId, bool $dryRun): array
    {
        $enriched = 0;
        $unmatched = 0;
        $skipped = 0;

        foreach ($rowsBySourceId as $sourceId => $row)
        {
            $payerName = trim((string) ($row['PAYER_NAME'] ?? ''));
            $payerIdType = trim((string) ($row['PAYER_ID_TYPE'] ?? ''));
            $payerIdNumber = trim((string) ($row['PAYER_ID_NUMBER'] ?? ''));

            if ($payerName === '' && $payerIdNumber === '')
            {
                $skipped++;

                continue;
            }

            /** @var PaymentSync|null $sync */
            $sync = PaymentSync::query()
                ->where('team_id', $team->id)
                ->where('provider', 'mercadopago')
                ->where('external_id', $sourceId)
                ->first();

            if ($sync === null)
            {
                $unmatched++;

                continue;
            }

            if ($dryRun)
            {
                $enriched++;

                continue;
            }

            $sync->mergeSettlementPayer(
                $payerName !== '' ? $payerName : null,
                $payerIdType !== '' ? $payerIdType : null,
                $payerIdNumber !== '' ? $payerIdNumber : null,
            );

            $enriched++;
        }

        return [$enriched, $unmatched, $skipped];
    }

    /**
     * Persist CSV rows into monthly bank_statements / bank_statement_lines.
     *
     * @param  array<string, array<string, string>>  $rowsBySourceId
     */
    public function persistStatementLines(Team $team, array $rowsBySourceId, ?string $originalFilename = null): int
    {
        $persisted = 0;

        foreach ($rowsBySourceId as $sourceId => $row)
        {
            $occurredAt = $this->parseTransactionDate((string) ($row['TRANSACTION_DATE'] ?? ''));
            $periodYear = (int) ($occurredAt?->year ?? now()->year);
            $periodMonth = (int) ($occurredAt?->month ?? now()->month);

            $statement = BankStatement::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'provider' => BankStatement::PROVIDER_MERCADOPAGO,
                    'period_year' => $periodYear,
                    'period_month' => $periodMonth,
                ],
                [
                    'source' => BankStatement::SOURCE_API,
                    'original_filename' => $originalFilename,
                ],
            );

            if ($originalFilename && blank($statement->original_filename))
            {
                $statement->forceFill(['original_filename' => $originalFilename])->save();
            }

            /** @var PaymentSync|null $sync */
            $sync = PaymentSync::query()
                ->where('team_id', $team->id)
                ->where('provider', 'mercadopago')
                ->where('external_id', $sourceId)
                ->first();

            $amount = $this->parseAmount((string) ($row['TRANSACTION_AMOUNT'] ?? '0'));
            $payerName = trim((string) ($row['PAYER_NAME'] ?? ''));
            $payerIdType = trim((string) ($row['PAYER_ID_TYPE'] ?? ''));
            $payerIdNumber = trim((string) ($row['PAYER_ID_NUMBER'] ?? ''));
            $reference = trim((string) ($row['EXTERNAL_REFERENCE'] ?? $row['PAY_BANK_TRANSFER_ID'] ?? ''));

            BankStatementLine::query()->updateOrCreate(
                [
                    'bank_statement_id' => $statement->id,
                    'external_id' => (string) $sourceId,
                ],
                [
                    'reference' => $reference !== '' ? $reference : null,
                    'occurred_at' => $occurredAt,
                    'amount' => $amount,
                    'currency' => 'ARS',
                    'payer_name' => $payerName !== '' ? $payerName : null,
                    'payer_id_type' => $payerIdType !== '' ? $payerIdType : null,
                    'payer_id_number' => $payerIdNumber !== '' ? $payerIdNumber : null,
                    'description' => trim((string) ($row['PAYMENT_METHOD_TYPE'] ?? '')) ?: null,
                    'payment_sync_id' => $sync?->id,
                    'raw' => $row,
                ],
            );

            $persisted++;
        }

        return $persisted;
    }

    private function parseTransactionDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '')
        {
            return null;
        }

        try
        {
            return Carbon::parse($value);
        } catch (\Throwable)
        {
            return null;
        }
    }

    private function parseAmount(string $value): float
    {
        $value = trim(str_replace(' ', '', $value));
        if ($value === '')
        {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.'))
        {
            $normalized = str_replace('.', '', $value);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($value, ','))
        {
            $normalized = str_replace(',', '.', $value);
        } else
        {
            $normalized = $value;
        }

        return round((float) $normalized, 2);
    }

    /**
     * Split into calendar months (1st → last day) in Argentina time, matching Mercado Pago report windows.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public function dateChunks(Carbon $beginDate, Carbon $endDate): array
    {
        $tz = self::REPORT_TIMEZONE;
        $chunks = [];
        // Use civil Y-m-d so UTC midnight on the 1st does not slip into the previous AR month.
        $cursor = Carbon::parse($beginDate->format('Y-m-d'), $tz)->startOfMonth();
        $end = Carbon::parse($endDate->format('Y-m-d'), $tz)->endOfMonth();

        while ($cursor->lte($end))
        {
            $chunkBegin = $cursor->clone()->startOfMonth();
            $chunkEnd = $cursor->clone()->endOfMonth();
            $chunks[] = [$chunkBegin, $chunkEnd];
            $cursor = $cursor->clone()->addMonthNoOverflow()->startOfMonth();
        }

        return $chunks;
    }

    public function monthAlreadyProcessed(Team $team, int $year, int $month): bool
    {
        $statement = BankStatement::query()
            ->where('team_id', $team->id)
            ->where('provider', BankStatement::PROVIDER_MERCADOPAGO)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (! $statement instanceof BankStatement)
        {
            return false;
        }

        if (filled($statement->original_filename))
        {
            return true;
        }

        return $statement->lines()->exists();
    }

    /**
     * Past months with a local statement are skipped; the current month is always eligible for refresh.
     */
    public function shouldSkipProcessedMonth(Team $team, Carbon $monthBegin, bool $force = false): bool
    {
        if ($force)
        {
            return false;
        }

        $tz = self::REPORT_TIMEZONE;
        $month = Carbon::parse($monthBegin->format('Y-m-d'), $tz)->startOfMonth();

        if ($month->isSameMonth(now($tz)))
        {
            return false;
        }

        return $this->monthAlreadyProcessed($team, (int) $month->year, (int) $month->month);
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(60)
            ->baseUrl('https://api.mercadopago.com');
    }

    private function ensureReportConfig(PendingRequest $client): void
    {
        $desiredColumns = array_map(
            fn (string $key): array => ['key' => $key],
            self::CONFIG_COLUMNS,
        );

        $get = $client->get('/v1/account/settlement_report/config');
        if ($get->successful())
        {
            $current = $get->json();
            $currentKeys = collect(data_get($current, 'columns', []))
                ->pluck('key')
                ->filter()
                ->map(fn ($key) => strtoupper((string) $key))
                ->values()
                ->all();

            $missing = array_diff(self::CONFIG_COLUMNS, $currentKeys);
            if ($missing === [])
            {
                return;
            }

            $mergedKeys = array_values(array_unique(array_merge($currentKeys, self::CONFIG_COLUMNS)));
            $payload = is_array($current) ? $current : [];
            $payload['columns'] = array_map(fn (string $key): array => ['key' => $key], $mergedKeys);
            unset($payload['scheduled']);

            $update = $client->put('/v1/account/settlement_report/config', $payload);
            if ($update->successful())
            {
                return;
            }

            Log::warning('MercadoPago settlement report config PUT failed; trying POST', [
                'status' => $update->status(),
                'body' => $update->body(),
            ]);
        }

        $create = $client->post('/v1/account/settlement_report/config', [
            'file_name_prefix' => 'humano-settlement-report',
            'include_withdraw' => false,
            'show_fee_prevision' => false,
            'display_timezone' => 'GMT-03',
            'header_language' => 'en',
            'frequency' => [
                'hour' => 3,
                'type' => 'monthly',
                'value' => 1,
            ],
            'columns' => $desiredColumns,
        ]);

        if (! $create->successful() && $create->status() !== 409)
        {
            throw new RuntimeException(
                'Unable to configure settlement report: HTTP '.$create->status().' '.$create->body(),
            );
        }
    }

    private function createAndWaitForReport(
        PendingRequest $client,
        Carbon $beginDate,
        Carbon $endDate,
        int $pollSeconds,
    ): string {
        $create = $client->post('/v1/account/settlement_report', [
            'begin_date' => $beginDate->clone()->utc()->format("Y-m-d\TH:i:s\Z"),
            'end_date' => $endDate->clone()->utc()->format("Y-m-d\TH:i:s\Z"),
        ]);

        if (! in_array($create->status(), [200, 201, 202], true))
        {
            throw new RuntimeException(
                'Unable to create settlement report: HTTP '.$create->status().' '.$create->body(),
            );
        }

        $reportId = (int) data_get($create->json(), 'id', 0);
        $deadline = microtime(true) + max(15, $pollSeconds);

        do
        {
            $list = $client->get('/v1/account/settlement_report/list');
            if ($list->successful())
            {
                $reports = $list->json();
                if (is_array($reports))
                {
                    $fileName = $this->pickReportFileName($reports, $reportId);
                    if ($fileName !== null)
                    {
                        return $fileName;
                    }
                }
            }

            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for Mercado Pago settlement report file.');
    }

    /**
     * @return list<string>
     */
    public function existingReportFileNames(PendingRequest $client, Carbon $beginDate, Carbon $endDate): array
    {
        $list = $client->get('/v1/account/settlement_report/list');
        if (! $list->successful() || ! is_array($list->json()))
        {
            return [];
        }

        $windowBegin = $beginDate->clone()->startOfDay();
        $windowEnd = $endDate->clone()->endOfDay();
        $files = [];

        foreach ($list->json() as $report)
        {
            if (! is_array($report))
            {
                continue;
            }

            $fileName = trim((string) ($report['file_name'] ?? ''));
            if ($fileName === '')
            {
                continue;
            }

            $status = strtolower(trim((string) ($report['status'] ?? 'processed')));
            if ($status !== '' && ! in_array($status, ['processed', 'available', 'ready'], true))
            {
                continue;
            }

            $reportBegin = isset($report['begin_date']) ? Carbon::parse((string) $report['begin_date']) : null;
            $reportEnd = isset($report['end_date']) ? Carbon::parse((string) $report['end_date']) : null;
            if ($reportBegin === null || $reportEnd === null)
            {
                continue;
            }

            if ($reportEnd->lt($windowBegin) || $reportBegin->gt($windowEnd))
            {
                continue;
            }

            $files[$fileName] = true;
        }

        return array_keys($files);
    }

    public function findExistingReportFileName(PendingRequest $client, Carbon $beginDate, Carbon $endDate): ?string
    {
        return $this->findBestExistingReportForMonth($client, $beginDate, $endDate);
    }

    /**
     * Prefer a report that fully covers the calendar month with the smallest span (exact month when available).
     */
    public function findBestExistingReportForMonth(PendingRequest $client, Carbon $beginDate, Carbon $endDate): ?string
    {
        $list = $client->get('/v1/account/settlement_report/list');
        if (! $list->successful() || ! is_array($list->json()))
        {
            return null;
        }

        $windowBegin = Carbon::parse($beginDate->format('Y-m-d'), self::REPORT_TIMEZONE)->startOfDay();
        $windowEnd = Carbon::parse($endDate->format('Y-m-d'), self::REPORT_TIMEZONE)->endOfDay();
        $bestFile = null;
        $bestScore = null;

        foreach ($list->json() as $report)
        {
            if (! is_array($report))
            {
                continue;
            }

            $fileName = trim((string) ($report['file_name'] ?? ''));
            if ($fileName === '')
            {
                continue;
            }

            $status = strtolower(trim((string) ($report['status'] ?? 'processed')));
            if ($status !== '' && ! in_array($status, ['processed', 'available', 'ready'], true))
            {
                continue;
            }

            $reportBegin = isset($report['begin_date']) ? Carbon::parse((string) $report['begin_date']) : null;
            $reportEnd = isset($report['end_date']) ? Carbon::parse((string) $report['end_date']) : null;
            if ($reportBegin === null || $reportEnd === null)
            {
                continue;
            }

            // Must fully cover the month window.
            if ($reportBegin->gt($windowBegin) || $reportEnd->lt($windowEnd))
            {
                continue;
            }

            $spanDays = max(1, $reportBegin->diffInDays($reportEnd));
            $score = $spanDays;

            if ($bestScore === null || $score < $bestScore)
            {
                $bestScore = $score;
                $bestFile = $fileName;
            }
        }

        if ($bestFile !== null)
        {
            return $bestFile;
        }

        // Fallback: any overlapping report (legacy 30-day windows from older runs).
        $files = $this->existingReportFileNames($client, $beginDate, $endDate);

        return $files[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     */
    private function pickReportFileName(array $reports, int $reportId): ?string
    {
        if ($reportId > 0)
        {
            foreach ($reports as $report)
            {
                if ((int) ($report['id'] ?? 0) !== $reportId)
                {
                    continue;
                }

                $fileName = trim((string) ($report['file_name'] ?? ''));

                return $fileName !== '' ? $fileName : null;
            }
        }

        usort($reports, function (array $left, array $right): int
        {
            return strcmp(
                (string) ($right['date_created'] ?? $right['generation_date'] ?? ''),
                (string) ($left['date_created'] ?? $left['generation_date'] ?? ''),
            );
        });

        foreach ($reports as $report)
        {
            $fileName = trim((string) ($report['file_name'] ?? ''));
            if ($fileName !== '')
            {
                return $fileName;
            }
        }

        return null;
    }

    private function downloadReport(PendingRequest $client, string $fileName): string
    {
        $response = $client
            ->withHeaders(['Accept' => 'text/csv, application/octet-stream, */*'])
            ->get('/v1/account/settlement_report/'.rawurlencode($fileName));

        if (! $response->successful())
        {
            throw new RuntimeException(
                'Unable to download settlement report: HTTP '.$response->status().' '.$response->body(),
            );
        }

        return (string) $response->body();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function parseCsvBySourceId(string $csv): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if ($lines === [])
        {
            return [];
        }

        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $headers = str_getcsv(array_shift($lines), $delimiter, '"', '\\');
        $headers = array_map(fn ($header) => strtoupper(trim((string) $header)), $headers);

        $bySource = [];
        foreach ($lines as $line)
        {
            if (trim($line) === '')
            {
                continue;
            }

            $values = str_getcsv($line, $delimiter, '"', '\\');
            $row = [];
            foreach ($headers as $index => $header)
            {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            $sourceId = trim((string) ($row['SOURCE_ID'] ?? ''));
            if ($sourceId === '')
            {
                continue;
            }

            $bySource[$sourceId] = $row;
        }

        return $bySource;
    }
}
