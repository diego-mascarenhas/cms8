<?php

namespace App\Support;

use App\Helpers\Helpers;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class InvoiceTableAmountFormatter
{
    public static function formatNative(float $amount, string $baseCurrency): string
    {
        return '<span class="fw-bold">'
            .Helpers::formatDecimal($amount)
            .' '
            .e(strtoupper($baseCurrency))
            .'</span>';
    }

    /**
     * Invoice document amount in native currency, with team reporting conversion underneath when different.
     */
    public static function formatWithReporting(
        float $nativeAmount,
        string $nativeCurrency,
        string $reportingCurrency,
        ?Carbon $date = null,
        string $toneClass = 'fw-medium',
    ): string {
        $nativeCurrency = strtoupper(trim($nativeCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));
        $date ??= now();

        $nativeHtml = '<span class="'.e($toneClass).' text-nowrap">'
            .e(Helpers::formatDecimal($nativeAmount).' '.($nativeCurrency !== '' ? $nativeCurrency : $reportingCurrency))
            .'</span>';

        if ($nativeCurrency === '' || $reportingCurrency === '' || $nativeCurrency === $reportingCurrency)
        {
            return $nativeHtml;
        }

        $rate = ExchangeRate::rateOnOrBeforeDate($nativeCurrency, $reportingCurrency, $date);
        $converted = $rate !== null && $rate > 0
            ? round($nativeAmount * $rate, 2)
            : ExchangeRate::convertOnOrBeforeDate($nativeAmount, $nativeCurrency, $reportingCurrency, $date);

        if ($converted === null)
        {
            return $nativeHtml;
        }

        $detail = Helpers::formatDecimal($converted).' '.$reportingCurrency;
        if ($rate !== null && $rate > 0)
        {
            $detail .= ' · '.__('Rate').' '.number_format(1 / $rate, 4, ',', '.');
        }

        return '<div class="text-end">'
            .$nativeHtml
            .'<br><small class="text-muted text-nowrap">≈ '.e($detail).'</small>'
            .'</div>';
    }

    /**
     * Plain-text exchange-rate observation for the invoice note section.
     */
    public static function reportingConversionNote(
        string $nativeCurrency,
        string $reportingCurrency,
        ?Carbon $date = null,
    ): ?string {
        $nativeCurrency = strtoupper(trim($nativeCurrency));
        $reportingCurrency = strtoupper(trim($reportingCurrency));
        $date ??= now();

        if ($nativeCurrency === '' || $reportingCurrency === '' || $nativeCurrency === $reportingCurrency)
        {
            return null;
        }

        $rate = ExchangeRate::rateOnOrBeforeDate($nativeCurrency, $reportingCurrency, $date);
        if ($rate === null || $rate <= 0)
        {
            return __('Exchange rate to team currency (:currency)', ['currency' => $reportingCurrency]);
        }

        return __('Exchange rate: 1 :reporting = :rate :native', [
            'reporting' => $reportingCurrency,
            'native' => $nativeCurrency,
            'rate' => number_format(1 / $rate, 4, ',', '.'),
        ]);
    }
}
