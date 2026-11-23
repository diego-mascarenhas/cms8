<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Services\Currency\FrankfurterExchangeRateClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InvoiceSettlementConverter
{
    /** @var array<string, float|null> */
    private static array $liveRateCache = [];

    public static function clearLiveRateCache(): void
    {
        self::$liveRateCache = [];
    }

    /**
     * Convert a document-currency amount to reporting currency.
     * Prefers the implied rate from cross-currency payments (bank settlement),
     * then stored Frankfurter/BCRA rates, then a live Frankfurter latest quote.
     */
    public static function convert(
        float $amount,
        string $fromCurrency,
        string $reportingCurrency,
        mixed $invoiceDate = null,
        ?Invoice $invoice = null,
        bool $fetchLiveRate = false,
    ): ?float {
        $fromCurrency = strtoupper(trim($fromCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));

        if ($fromCurrency === '' || $reportingCurrency === '')
        {
            return null;
        }

        if ($fromCurrency === $reportingCurrency)
        {
            return round($amount, 2);
        }

        $settlementRate = self::settlementRate($invoice, $fromCurrency, $reportingCurrency);

        if ($settlementRate !== null)
        {
            return round($amount * $settlementRate, 2);
        }

        if (blank($invoiceDate))
        {
            $converted = ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);

            return $converted ?? ($fetchLiveRate ? self::convertWithLiveRate($amount, $fromCurrency, $reportingCurrency) : null);
        }

        $conversionDate = Carbon::parse($invoiceDate)->endOfMonth();
        $converted = ExchangeRate::convertOnOrBeforeDate($amount, $fromCurrency, $reportingCurrency, $conversionDate);

        if ($converted !== null)
        {
            return $converted;
        }

        $converted = ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);

        return $converted ?? ($fetchLiveRate ? self::convertWithLiveRate($amount, $fromCurrency, $reportingCurrency) : null);
    }

    private static function convertWithLiveRate(float $amount, string $fromCurrency, string $reportingCurrency): ?float
    {
        $rate = self::liveRate($fromCurrency, $reportingCurrency);

        return $rate !== null && $rate > 0 ? round($amount * $rate, 2) : null;
    }

    private static function liveRate(string $fromCurrency, string $reportingCurrency): ?float
    {
        $cacheKey = $fromCurrency.'->'.$reportingCurrency;
        if (array_key_exists($cacheKey, self::$liveRateCache))
        {
            return self::$liveRateCache[$cacheKey];
        }

        try
        {
            $client = FrankfurterExchangeRateClient::fromConfig();
            $result = $client->fetchLatest($fromCurrency, [$reportingCurrency]);
            $rate = ($result['success'] ?? false) ? (float) ($result['rates'][$reportingCurrency] ?? 0) : 0.0;

            if ($rate <= 0)
            {
                $inverse = $client->fetchLatest($reportingCurrency, [$fromCurrency]);
                $inverseRate = ($inverse['success'] ?? false) ? (float) ($inverse['rates'][$fromCurrency] ?? 0) : 0.0;
                $rate = $inverseRate > 0 ? (1 / $inverseRate) : 0.0;
            }

            if ($rate > 0)
            {
                $date = (string) ($result['date'] ?? now()->toDateString());
                ExchangeRate::storeDailyIfChanged($fromCurrency, $reportingCurrency, $date, $rate);
                self::$liveRateCache[$cacheKey] = $rate;

                return $rate;
            }
        } catch (\Throwable $e)
        {
            Log::warning('InvoiceSettlementConverter live FX fetch failed', [
                'from' => $fromCurrency,
                'to' => $reportingCurrency,
                'error' => $e->getMessage(),
            ]);
        }

        self::$liveRateCache[$cacheKey] = null;

        return null;
    }

    /**
     * Bank / account currency per 1 unit of document currency, from payments.
     */
    public static function settlementRate(?Invoice $invoice, string $fromCurrency, string $reportingCurrency): ?float
    {
        if (! $invoice instanceof Invoice)
        {
            return null;
        }

        $fromCurrency = strtoupper(trim($fromCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));

        if ($fromCurrency === '' || $reportingCurrency === '' || $fromCurrency === $reportingCurrency)
        {
            return null;
        }

        $invoice->loadMissing(['payments.account.currency']);

        $paidReporting = 0.0;
        $appliedDocument = 0.0;

        foreach ($invoice->payments as $payment)
        {
            $accountCurrency = strtoupper(trim((string) ($payment->account?->currency?->code ?? '')));

            if ($accountCurrency !== $reportingCurrency)
            {
                continue;
            }

            $documentAmount = $payment->document_amount !== null
                ? (float) $payment->document_amount
                : 0.0;

            if ($documentAmount <= 0)
            {
                continue;
            }

            $paidReporting += (float) $payment->amount;
            $appliedDocument += $documentAmount;
        }

        if ($paidReporting <= 0 || $appliedDocument <= 0)
        {
            return null;
        }

        return $paidReporting / $appliedDocument;
    }
}
