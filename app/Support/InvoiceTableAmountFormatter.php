<?php

namespace App\Support;

use App\Helpers\Helpers;

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
}
