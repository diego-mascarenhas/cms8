<?php

namespace App\Support;

use App\Helpers\Helpers;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

class PaymentInvoiceLinkOptionFormatter
{
    public static function label(Invoice $invoice): string
    {
        $parts = [
            $invoice->number,
            $invoice->date
                ? Carbon::parse($invoice->date)->format('d/m/Y')
                : '—',
        ];

        if ($invoice->enterprise)
        {
            $parts[] = $invoice->enterprise->name;
        }

        $parts[] = Helpers::formatDecimal((float) $invoice->balance).' '.$invoice->currency_code;

        return implode(' — ', $parts);
    }

    public static function paidLinkLabel(Invoice $invoice): string
    {
        $parts = [
            $invoice->number,
            $invoice->date
                ? Carbon::parse($invoice->date)->format('d/m/Y')
                : '—',
        ];

        if ($invoice->enterprise)
        {
            $parts[] = $invoice->enterprise->name;
        }

        $parts[] = Helpers::formatDecimal((float) $invoice->total_amount).' '.$invoice->currency_code;

        return implode(' — ', $parts);
    }
}
