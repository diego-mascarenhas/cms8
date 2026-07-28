<?php

namespace App\Services\Finance;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentAccountStatementUploadService
{
    private const DISK = 'originals';

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{statement: BankStatement, validation: array<string, mixed>}>
     */
    public function uploadMany(
        PaymentAccount $account,
        array $files,
        ?int $periodYear = null,
        ?int $periodMonth = null,
    ): array {
        $results = [];

        foreach ($files as $file)
        {
            if (! $file instanceof UploadedFile)
            {
                continue;
            }

            $results[] = $this->uploadOne($account, $file, $periodYear, $periodMonth);
        }

        if ($results === [])
        {
            throw new RuntimeException('No files were uploaded.');
        }

        return $results;
    }

    /**
     * @return array{statement: BankStatement, validation: array<string, mixed>}
     */
    public function uploadOne(
        PaymentAccount $account,
        UploadedFile $file,
        ?int $periodYear = null,
        ?int $periodMonth = null,
    ): array {
        $originalName = (string) $file->getClientOriginalName();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $isCsv = in_array($extension, ['csv', 'txt'], true);

        $csvRows = $isCsv ? $this->parseCsvRows((string) $file->get()) : [];
        [$year, $month] = $this->resolvePeriod(
            $periodYear,
            $periodMonth,
            $originalName,
            $csvRows,
        );

        $account->loadMissing('currency');

        $storedPath = $this->storeFile($account, $file, $year, $month);

        $statement = BankStatement::query()->create([
            'team_id' => (int) $account->team_id,
            'payment_account_id' => (int) $account->id,
            'provider' => BankStatement::PROVIDER_UPLOAD,
            'period_year' => $year,
            'period_month' => $month,
            'source' => BankStatement::SOURCE_UPLOAD,
            'original_filename' => $originalName,
            'storage_path' => $storedPath,
            'disk' => self::DISK,
            'mime_type' => $file->getMimeType() ?: ($isCsv ? 'text/csv' : 'application/octet-stream'),
            'file_size' => $file->getSize() ?: null,
        ]);

        if ($csvRows !== [])
        {
            $this->persistCsvLines($statement, $csvRows, $account);
        }

        $validation = $this->validateAgainstPayments($statement, $account);
        $statement->forceFill(['validation_summary' => $validation])->save();

        return [
            'statement' => $statement->fresh(['lines']) ?? $statement,
            'validation' => $validation,
        ];
    }

    /**
     * @return array{
     *     matched: int,
     *     statement_only: int,
     *     payment_only: int,
     *     line_count: int,
     *     payment_count: int
     * }
     */
    public function validateAgainstPayments(BankStatement $statement, PaymentAccount $account): array
    {
        $lines = $statement->lines()->get();
        $payments = Payment::withoutGlobalScopes()
            ->where('team_id', $account->team_id)
            ->where('account_id', $account->id)
            ->whereYear('date', $statement->period_year)
            ->whereMonth('date', $statement->period_month)
            ->get();

        $matchedPaymentIds = [];
        $matched = 0;
        $statementOnly = 0;

        foreach ($lines as $line)
        {
            $payment = $this->findMatchingPayment($line, $payments, $matchedPaymentIds);

            if ($payment instanceof Payment)
            {
                $matchedPaymentIds[$payment->id] = true;
                $matched++;
                $line->forceFill([
                    'payment_id' => $payment->id,
                    'match_status' => 'matched',
                ])->save();

                continue;
            }

            $statementOnly++;
            $line->forceFill([
                'payment_id' => null,
                'match_status' => 'statement_only',
            ])->save();
        }

        $paymentOnly = $payments
            ->filter(fn (Payment $payment): bool => ! isset($matchedPaymentIds[$payment->id]))
            ->count();

        return [
            'matched' => $matched,
            'statement_only' => $statementOnly,
            'payment_only' => $paymentOnly,
            'line_count' => $lines->count(),
            'payment_count' => $payments->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Payment>  $payments
     * @param  array<int, true>  $matchedPaymentIds
     */
    private function findMatchingPayment(
        BankStatementLine $line,
        $payments,
        array $matchedPaymentIds,
    ): ?Payment {
        $lineAmount = round((float) $line->amount, 2);
        $lineDate = $line->occurred_at?->toDateString();
        $lineReference = mb_strtolower(trim((string) $line->reference));

        foreach ($payments as $payment)
        {
            if (isset($matchedPaymentIds[$payment->id]))
            {
                continue;
            }

            $paymentAmount = round((float) $payment->amount, 2);
            if (abs($paymentAmount - abs($lineAmount)) > 0.05
                && abs($paymentAmount - $lineAmount) > 0.05)
            {
                continue;
            }

            $paymentDate = optional($payment->date)->toDateString()
                ?? (string) $payment->date;
            $dateOk = $lineDate === null
                || abs(Carbon::parse($paymentDate)->diffInDays(Carbon::parse($lineDate))) <= 2;

            if (! $dateOk)
            {
                continue;
            }

            if ($lineReference !== '')
            {
                $haystack = mb_strtolower(trim((string) ($payment->remarks ?? '')));
                $sourceRef = mb_strtolower(trim((string) ($payment->source_reference_id ?? '')));
                if ($haystack !== '' && str_contains($haystack, $lineReference))
                {
                    return $payment;
                }
                if ($sourceRef !== '' && ($sourceRef === $lineReference || str_contains($sourceRef, $lineReference)))
                {
                    return $payment;
                }
            }

            // Amount + date match is enough when no conflicting reference.
            return $payment;
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function persistCsvLines(BankStatement $statement, array $rows, PaymentAccount $account): void
    {
        $currency = strtoupper((string) ($account->currency?->code ?? 'ARS'));

        foreach ($rows as $index => $row)
        {
            $occurredAt = $this->parseDate($this->rowValue($row, [
                'TRANSACTION_DATE', 'DATE', 'FECHA', 'VALUE_DATE', 'BOOKING_DATE',
            ]));
            $amount = $this->parseAmount($this->rowValue($row, [
                'TRANSACTION_AMOUNT', 'AMOUNT', 'IMPORTE', 'MONTO', 'CREDIT', 'DEBIT',
            ]));
            $reference = $this->rowValue($row, [
                'EXTERNAL_REFERENCE', 'REFERENCE', 'REFERENCIA', 'CONCEPTO', 'PAY_BANK_TRANSFER_ID',
            ]);
            $description = $this->rowValue($row, [
                'DESCRIPTION', 'DESCRIPCION', 'DETALLE', 'PAYMENT_METHOD_TYPE', 'MEMO',
            ]);
            $externalId = $this->rowValue($row, [
                'SOURCE_ID', 'EXTERNAL_ID', 'ID', 'OPERATION_ID',
            ]);

            if ($externalId === '')
            {
                $externalId = 'row-'.($index + 1).'-'.substr(sha1(json_encode($row) ?: (string) $index), 0, 12);
            }

            BankStatementLine::query()->updateOrCreate(
                [
                    'bank_statement_id' => $statement->id,
                    'external_id' => $externalId,
                ],
                [
                    'reference' => $reference !== '' ? Str::limit($reference, 255, '') : null,
                    'occurred_at' => $occurredAt,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payer_name' => $this->nullableString($this->rowValue($row, ['PAYER_NAME', 'TITULAR', 'COUNTERPARTY'])),
                    'payer_id_type' => $this->nullableString($this->rowValue($row, ['PAYER_ID_TYPE'])),
                    'payer_id_number' => $this->nullableString($this->rowValue($row, ['PAYER_ID_NUMBER'])),
                    'description' => $description !== '' ? Str::limit($description, 1000, '') : null,
                    'raw' => $row,
                ],
            );
        }
    }

    /**
     * @return list<array<string, string>>
     */
    public function parseCsvRows(string $csv): array
    {
        $csv = preg_replace("/^\xEF\xBB\xBF/", '', $csv) ?? $csv;
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if ($lines === [])
        {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(
            fn ($header) => strtoupper(trim((string) $header)),
            str_getcsv(array_shift($lines), $delimiter),
        );

        $rows = [];
        foreach ($lines as $line)
        {
            if (trim($line) === '')
            {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($headers as $index => $header)
            {
                if ($header === '')
                {
                    continue;
                }
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            if ($row !== [])
            {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $csvRows
     * @return array{0: int, 1: int}
     */
    public function resolvePeriod(
        ?int $periodYear,
        ?int $periodMonth,
        string $filename,
        array $csvRows = [],
    ): array {
        if ($periodYear && $periodMonth && $periodMonth >= 1 && $periodMonth <= 12)
        {
            return [$periodYear, $periodMonth];
        }

        $fromName = $this->inferPeriodFromFilename($filename);
        if ($fromName !== null)
        {
            return $fromName;
        }

        $fromCsv = $this->inferPeriodFromRows($csvRows);
        if ($fromCsv !== null)
        {
            return $fromCsv;
        }

        $now = now('America/Argentina/Buenos_Aires');

        return [(int) $now->year, (int) $now->month];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function inferPeriodFromFilename(string $filename): ?array
    {
        if (preg_match('/(20\d{2})[_-](\d{1,2})/', $filename, $matches) === 1)
        {
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12)
            {
                return [(int) $matches[1], $month];
            }
        }

        if (preg_match('/(\d{1,2})[_-](20\d{2})/', $filename, $matches) === 1)
        {
            $month = (int) $matches[1];
            if ($month >= 1 && $month <= 12)
            {
                return [(int) $matches[2], $month];
            }
        }

        if (preg_match('/(20\d{2})(\d{2})/', $filename, $matches) === 1)
        {
            $month = (int) $matches[2];
            if ($month >= 1 && $month <= 12)
            {
                return [(int) $matches[1], $month];
            }
        }

        $months = [
            'enero' => 1, 'february' => 2, 'febrero' => 2, 'marzo' => 3, 'march' => 3,
            'abril' => 4, 'april' => 4, 'mayo' => 5, 'may' => 5, 'junio' => 6, 'june' => 6,
            'julio' => 7, 'july' => 7, 'agosto' => 8, 'august' => 8,
            'septiembre' => 9, 'september' => 9, 'octubre' => 10, 'october' => 10,
            'noviembre' => 11, 'november' => 11, 'diciembre' => 12, 'december' => 12,
        ];
        $lower = mb_strtolower($filename);
        foreach ($months as $name => $month)
        {
            if (str_contains($lower, $name) && preg_match('/(20\d{2})/', $filename, $yearMatch) === 1)
            {
                return [(int) $yearMatch[1], $month];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{0: int, 1: int}|null
     */
    private function inferPeriodFromRows(array $rows): ?array
    {
        $counts = [];
        foreach ($rows as $row)
        {
            $date = $this->parseDate($this->rowValue($row, [
                'TRANSACTION_DATE', 'DATE', 'FECHA', 'VALUE_DATE', 'BOOKING_DATE',
            ]));
            if ($date === null)
            {
                continue;
            }

            $key = $date->format('Y-n');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        if ($counts === [])
        {
            return null;
        }

        arsort($counts);
        $top = array_key_first($counts);
        [$year, $month] = array_map('intval', explode('-', (string) $top));

        return [$year, $month];
    }

    private function storeFile(PaymentAccount $account, UploadedFile $file, int $year, int $month): string
    {
        $teamHash = Team::generateTeamHash((int) $account->team_id);
        $originalName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) $file->getClientOriginalExtension()) ?: 'bin';
        $normalized = Str::slug(Str::ascii($originalName)) ?: 'extracto';
        $fileName = $normalized.'-'.now()->format('YmdHis').'.'.$extension;
        $directory = sprintf(
            'bank-statements/%s/%d/%04d-%02d',
            $teamHash,
            $account->id,
            $year,
            $month,
        );

        $path = $file->storeAs($directory, $fileName, self::DISK);
        if (! is_string($path) || $path === '')
        {
            throw new RuntimeException('Unable to store bank statement file.');
        }

        return $path;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, string>  $row
     */
    private function rowValue(array $row, array $keys): string
    {
        foreach ($keys as $key)
        {
            $upper = strtoupper($key);
            if (isset($row[$upper]) && trim($row[$upper]) !== '')
            {
                return trim($row[$upper]);
            }
        }

        return '';
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function parseDate(string $value): ?Carbon
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

    public function downloadStream(BankStatement $statement)
    {
        if (! filled($statement->storage_path) || ! filled($statement->disk))
        {
            throw new RuntimeException('Statement file is not available for download.');
        }

        if (! Storage::disk((string) $statement->disk)->exists((string) $statement->storage_path))
        {
            throw new RuntimeException('Statement file is missing from storage.');
        }

        return Storage::disk((string) $statement->disk)->download(
            (string) $statement->storage_path,
            (string) ($statement->original_filename ?: basename((string) $statement->storage_path)),
        );
    }
}
