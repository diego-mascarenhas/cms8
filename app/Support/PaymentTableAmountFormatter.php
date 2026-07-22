<?php

namespace App\Support;

use App\Helpers\Helpers;
use App\Models\ExchangeRate;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentTableAmountFormatter
{
    public static function format(float $amount, string $currencyCode): string
    {
        return '<span class="fw-medium">'
            .Helpers::formatDecimal($amount)
            .' '
            .e(strtoupper($currencyCode))
            .'</span>';
    }

    public static function formatIncome(float $amount, string $currencyCode): string
    {
        return '<span class="text-success fw-bold">'
            .Helpers::formatDecimal($amount)
            .' '
            .e(strtoupper($currencyCode))
            .'</span>';
    }

    public static function formatExpense(float $amount, string $currencyCode): string
    {
        return '<span class="text-danger fw-bold">'
            .Helpers::formatDecimal($amount)
            .' '
            .e(strtoupper($currencyCode))
            .'</span>';
    }

    /**
     * Dual amount like Stripe: reporting currency on top, native + exchange rate below when different.
     */
    public static function formatConverted(
        float $nativeAmount,
        string $nativeCurrency,
        string $reportingCurrency,
        ?Carbon $date = null,
        string $toneClass = 'text-success fw-bold',
    ): string {
        $nativeCurrency = strtoupper(trim($nativeCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));
        $date ??= now();

        if ($nativeCurrency === '' || $reportingCurrency === '' || $nativeCurrency === $reportingCurrency)
        {
            return '<div class="text-end"><span class="'.e($toneClass).'">'
                .Helpers::formatDecimal($nativeAmount)
                .' '
                .e($nativeCurrency !== '' ? $nativeCurrency : $reportingCurrency)
                .'</span></div>';
        }

        $rate = ExchangeRate::rateOnOrBeforeDate($nativeCurrency, $reportingCurrency, $date);
        $converted = $rate !== null && $rate > 0
            ? round($nativeAmount * $rate, 2)
            : ExchangeRate::convertOnOrBeforeDate($nativeAmount, $nativeCurrency, $reportingCurrency, $date);

        $main = $converted !== null
            ? Helpers::formatDecimal($converted).' '.$reportingCurrency
            : Helpers::formatDecimal($nativeAmount).' '.$nativeCurrency;

        $detail = Helpers::formatDecimal($nativeAmount).' '.$nativeCurrency;
        if ($rate !== null && $rate > 0)
        {
            // Show reporting → native (same convention as Hacienda CSV / Stripe).
            $detail .= ' · '.__('Rate').' '.number_format(1 / $rate, 4, ',', '.');
        }

        return '<div class="text-end">'
            .'<span class="'.e($toneClass).'">'.e($main).'</span>'
            .'<br><small class="text-muted">'.e($detail).'</small>'
            .'</div>';
    }

    public static function taxIdForPayment(Payment $payment): string
    {
        $billingTaxId = trim((string) ($payment->invoice?->billingAddress?->identification_number ?? ''));
        if ($billingTaxId !== '')
        {
            return self::cleanTaxId($billingTaxId);
        }

        return self::cleanTaxId(trim((string) ($payment->invoice?->stripeInvoiceSync?->customer_tax_id ?? '')));
    }

    public static function countryForPayment(Payment $payment): string
    {
        $billingCountry = trim((string) ($payment->invoice?->billingAddress?->country ?? ''));
        if ($billingCountry !== '')
        {
            return strtoupper($billingCountry);
        }

        $syncCountry = trim((string) ($payment->invoice?->stripeInvoiceSync?->customer_address_country ?? ''));
        if ($syncCountry !== '')
        {
            return strtoupper($syncCountry);
        }

        return strtoupper(trim((string) ($payment->enterprise?->country ?? '')));
    }

    public static function currencyBadge(string $currencyCode): string
    {
        $code = strtoupper(trim($currencyCode));
        if ($code === '')
        {
            return '<span class="text-muted">—</span>';
        }

        $color = match ($code)
        {
            'EUR' => 'bg-label-success',
            'ARS' => 'bg-label-warning',
            'USD' => 'bg-label-info',
            default => 'bg-label-secondary',
        };

        return '<span class="badge '.$color.'">'.e($code).'</span>';
    }

    public static function countryBadge(string $country): string
    {
        $country = strtoupper(trim($country));
        if ($country === '')
        {
            return '<span class="text-muted">—</span>';
        }

        return '<span class="badge bg-label-primary">'.e($country).'</span>';
    }

    private static function cleanTaxId(string $taxId): string
    {
        if ($taxId === '')
        {
            return '';
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $taxId, $matches) === 1)
        {
            return trim($matches[1]);
        }

        if (preg_match('/^([\d\-]+)([a-z_]+)$/i', $taxId, $matches) === 1)
        {
            return trim($matches[1]);
        }

        return $taxId;
    }
}
