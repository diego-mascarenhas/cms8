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

        $paidAt = MercadoPagoPaidInvoiceLinker::stripePaidAt($invoice);
        if ($paidAt !== null)
        {
            $parts[] = __('payment_sync.mercadopago.paid_at_label', [
                'date' => $paidAt->format('d/m/Y H:i'),
            ]);
        }

        if ($invoice->enterprise)
        {
            $parts[] = $invoice->enterprise->name;
        }

        $parts[] = Helpers::formatDecimal((float) $invoice->total_amount).' '.$invoice->currency_code;

        return implode(' — ', $parts);
    }
}
