<?php

namespace App\Support;

use App\Models\ExchangeRate;

class StripeInvoiceMetrics
{
    /**
     * Display currency code for aggregated invoice amounts (Stripe uses lowercase ISO codes).
     *
     * @param  array<int, object>  $invoices
     */
    public static function displayCurrencyForInvoices(array $invoices, string $default = 'EUR'): string
    {
        if ($invoices === [])
        {
            return $default;
        }

        $currency = $invoices[0]->currency ?? $default;

        return strtoupper((string) $currency);
    }

    /**
     * Prefer currency from unpaid invoices (open + uncollectible) so dashboard labels match pending balances.
     * If the contact is in Argentina and any invoice is in ARS, prefer ARS for the label when still ambiguous.
     *
     * @param  array<int, object>  $paidInvoices
     * @param  array<int, object>  $openInvoices
     * @param  array<int, object>  $uncollectibleInvoices
     */
    public static function displayCurrencyForStripeInvoiceGroups(
        array $paidInvoices,
        array $openInvoices,
        array $uncollectibleInvoices,
        string $default = 'EUR',
        ?string $contactCountryCode = null,
    ): string {
        foreach (array_merge($openInvoices, $uncollectibleInvoices) as $invoice)
        {
            if (isset($invoice->currency) && $invoice->currency !== '')
            {
                return strtoupper((string) $invoice->currency);
            }
        }

        $merged = array_merge($paidInvoices, $openInvoices, $uncollectibleInvoices);
        if ($merged === [])
        {
            return $default;
        }

        $fromFirst = strtoupper((string) ($merged[0]->currency ?? $default));

        if ($contactCountryCode === 'ar')
        {
            foreach ($merged as $invoice)
            {
                if (isset($invoice->currency) && strtoupper((string) $invoice->currency) === 'ARS')
                {
                    return 'ARS';
                }
            }
        }

        return $fromFirst;
    }

    /**
     * Paid invoice amount in major units (matches balance table rows).
     */
    public static function stripePaidInvoiceAmount(object $invoice): float
    {
        $cents = (int) ($invoice->amount_paid ?? 0);

        if ($cents <= 0)
        {
            $cents = (int) ($invoice->total ?? $invoice->amount_due ?? 0);
        }

        return $cents / 100;
    }

    /**
     * Open / uncollectible invoice amount in major units (matches balance table rows).
     */
    public static function stripeUnpaidInvoiceAmount(object $invoice): float
    {
        $cents = (int) ($invoice->amount_due ?? $invoice->amount_remaining ?? $invoice->total ?? 0);

        return $cents / 100;
    }

    /**
     * Summary totals for the contact balance cards (same rows as the tables below).
     *
     * @param  array<int, array<string, mixed>>  $paidRows
     * @param  array<int, array<string, mixed>>  $unpaidRows
     * @return array{
     *     total_paid: string,
     *     unpaid: string,
     *     paid_by_currency: array<string, float>,
     *     unpaid_by_currency: array<string, float>,
     *     total_paid_raw: float,
     *     unpaid_raw: float,
     * }
     */
    public static function contactBalanceMetrics(array $paidRows, array $unpaidRows, ?string $primaryCurrency = null): array
    {
        $paidByCurrency = self::sumAmountsByCurrency($paidRows);
        $unpaidByCurrency = self::sumAmountsByCurrency($unpaidRows);
        $primaryCurrency = strtoupper(trim((string) ($primaryCurrency ?? config('cashier.currency', 'usd'))));

        return [
            'total_paid' => self::formatMetricTotalsWithPrimaryEquivalent($paidByCurrency, $primaryCurrency),
            'unpaid' => self::formatMetricTotalsWithPrimaryEquivalent($unpaidByCurrency, $primaryCurrency),
            'paid_by_currency' => $paidByCurrency,
            'unpaid_by_currency' => $unpaidByCurrency,
            'total_paid_raw' => array_sum($paidByCurrency),
            'unpaid_raw' => array_sum($unpaidByCurrency),
        ];
    }

    /**
     * Resolve metric card text from controller metrics or by summing visible table rows.
     *
     * @param  array<string, mixed>|null  $metrics
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function metricCardDisplay(?array $metrics, string $metricKey, array $rows): string
    {
        $fromMetrics = is_array($metrics) ? trim((string) ($metrics[$metricKey] ?? '')) : '';

        if ($fromMetrics !== '' && $fromMetrics !== '0.00')
        {
            return $fromMetrics;
        }

        return self::formatMetricTotalsForDisplay(self::sumAmountsByCurrency($rows));
    }

    /**
     * Sum invoice row amounts grouped by ISO currency (same rows as shown in balance tables).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float> Uppercase currency code => total amount
     */
    public static function sumAmountsByCurrency(array $rows): array
    {
        $sums = [];
        foreach ($rows as $row)
        {
            $code = strtoupper(trim((string) ($row['currency'] ?? '')));
            if ($code === '')
            {
                $code = 'XXX';
            }
            $sums[$code] = ($sums[$code] ?? 0.0) + (float) ($row['amount'] ?? 0);
        }
        ksort($sums);

        return $sums;
    }

    /**
     * Display string for one or more currency buckets (e.g. "62,727.27 ARS" or "10.00 EUR · 100.00 ARS").
     *
     * @param  array<string, float>  $sumsByCurrency
     */
    public static function formatMetricTotalsForDisplay(array $sumsByCurrency): string
    {
        if ($sumsByCurrency === [])
        {
            return '0.00';
        }

        $parts = [];
        foreach ($sumsByCurrency as $currency => $amount)
        {
            if ($currency === 'XXX')
            {
                $parts[] = number_format($amount, 2);
            } else
            {
                $parts[] = number_format($amount, 2).' '.$currency;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Sum amounts from multiple currencies into one target currency using {@see ExchangeRate} (latest rates).
     * Returns null if any bucket has unknown currency (XXX) or a rate pair is missing.
     *
     * @param  array<string, float>  $sumsByCurrency
     */
    public static function sumAmountsConvertedToCurrency(array $sumsByCurrency, string $targetCurrency): ?float
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        if ($targetCurrency === '' || $sumsByCurrency === [])
        {
            return null;
        }

        $total = 0.0;
        foreach ($sumsByCurrency as $currency => $amount)
        {
            $from = strtoupper((string) $currency);
            if ($from === 'XXX')
            {
                return null;
            }

            $amount = (float) $amount;
            if ($from === $targetCurrency)
            {
                $total += $amount;

                continue;
            }

            $converted = ExchangeRate::convert($amount, $from, $targetCurrency);
            if ($converted === null)
            {
                return null;
            }

            $total += $converted;
        }

        return $total;
    }

    /**
     * Same as {@see formatMetricTotalsForDisplay} plus an approximate total in the app's principal currency
     * (config `cashier.currency`, e.g. CASHIER_CURRENCY) when conversion is available and useful.
     *
     * @param  array<string, float>  $sumsByCurrency
     */
    public static function formatMetricTotalsWithPrimaryEquivalent(array $sumsByCurrency, string $primaryCurrency): string
    {
        $line = self::formatMetricTotalsForDisplay($sumsByCurrency);
        $primaryCurrency = strtoupper(trim($primaryCurrency));
        if ($primaryCurrency === '' || $sumsByCurrency === [])
        {
            return $line;
        }

        $onlyPrimary = count($sumsByCurrency) === 1 && array_key_exists($primaryCurrency, $sumsByCurrency);
        if ($onlyPrimary)
        {
            return $line;
        }

        $equiv = self::sumAmountsConvertedToCurrency($sumsByCurrency, $primaryCurrency);
        if ($equiv === null)
        {
            return $line;
        }

        return $line.' (≈ '.number_format($equiv, 2).' '.$primaryCurrency.')';
    }
}
