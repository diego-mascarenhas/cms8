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
     * Mercado Pago rejects creation above ~60 days; shorter chunks also finish faster.
     */
    private const MAX_REPORT_DAYS = 30;

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
     * @return array{enriched: int, report_rows: int, unmatched: int, skipped: int, chunks: int, statement_lines: int}
     */
    public function enrichTeam(
        Team $team,
        Carbon $beginDate,
        Carbon $endDate,
        bool $dryRun = false,
        int $pollSeconds = 90,
    ): array {
        $token = trim((string) $team->getSetting('mercadopago_access_token'));
        if ($token === '')
        {
            throw new RuntimeException('Missing mercadopago_access_token for team '.$team->id);
        }

        $client = $this->http($token);
        $this->ensureReportConfig($client);

        $enriched = 0;
        $unmatched = 0;
        $skipped = 0;
        $reportRows = 0;
        $chunks = 0;
        $statementLines = 0;

        foreach ($this->dateChunks($beginDate, $endDate) as $chunk)
        {
            $chunks++;
            [$chunkBegin, $chunkEnd] = $chunk;

            $fileName = $this->createAndWaitForReport($client, $chunkBegin, $chunkEnd, $pollSeconds);
            $csv = $this->downloadReport($client, $fileName);
            $rowsBySourceId = $this->parseCsvBySourceId($csv);
            $reportRows += count($rowsBySourceId);

            if (! $dryRun)
            {
                $statementLines += $this->persistStatementLines($team, $rowsBySourceId, $fileName);
            }

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
        }

        return [
            'enriched' => $enriched,
            'report_rows' => $reportRows,
            'unmatched' => $unmatched,
            'skipped' => $skipped,
            'chunks' => $chunks,
            'statement_lines' => $statementLines,
        ];
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
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public function dateChunks(Carbon $beginDate, Carbon $endDate): array
    {
        $chunks = [];
        $cursor = $beginDate->clone()->startOfDay();
        $end = $endDate->clone()->endOfDay();

        while ($cursor->lte($end))
        {
            $chunkEnd = $cursor->clone()->addDays(self::MAX_REPORT_DAYS - 1)->endOfDay();
            if ($chunkEnd->gt($end))
            {
                $chunkEnd = $end->clone();
            }

            $chunks[] = [$cursor->clone(), $chunkEnd];
            $cursor = $chunkEnd->clone()->addSecond()->startOfDay();
        }

        return $chunks;
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
