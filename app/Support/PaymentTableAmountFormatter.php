<?php

namespace App\Support;

use App\Helpers\Helpers;

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

    public static function formatExpense(float $amount, string $currencyCode): string
    {
        return '<span class="text-danger fw-bold">- '
            .Helpers::formatDecimal($amount)
            .' '
            .e(strtoupper($currencyCode))
            .'</span>';
    }
}
