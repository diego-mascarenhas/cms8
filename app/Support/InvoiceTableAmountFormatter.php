<?php

namespace App\Support;

class InvoiceTableAmountFormatter
{
    public static function formatNative(float $amount, string $baseCurrency): string
    {
        return '<span class="fw-bold">'
            .number_format($amount, 2, '.', ',')
            .' '
            .e(strtoupper($baseCurrency))
            .'</span>';
    }
}
