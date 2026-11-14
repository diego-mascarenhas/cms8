<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceSettlementConverter
{
    /**
     * Convert a document-currency amount to reporting currency.
     * Prefers the implied rate from cross-currency payments (bank settlement),
     * then falls back to Frankfurter mid-market at end of invoice month.
     */
    public static function convert(
        float $amount,
        string $fromCurrency,
        string $reportingCurrency,
        mixed $invoiceDate = null,
        ?Invoice $invoice = null,
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
            return ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);
        }

        $conversionDate = Carbon::parse($invoiceDate)->endOfMonth();
        $converted = ExchangeRate::convertOnOrBeforeDate($amount, $fromCurrency, $reportingCurrency, $conversionDate);

        if ($converted !== null)
        {
            return $converted;
        }

        return ExchangeRate::convert($amount, $fromCurrency, $reportingCurrency);
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
