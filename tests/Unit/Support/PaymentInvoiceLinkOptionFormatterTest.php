<?php

namespace Tests\Unit\Support;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Support\PaymentInvoiceLinkOptionFormatter;
use Tests\TestCase;

class PaymentInvoiceLinkOptionFormatterTest extends TestCase
{
    public function test_label_includes_number_date_enterprise_and_balance_with_currency(): void
    {
        $enterprise = new Enterprise(['name' => 'Diaz Williams']);
        $currency = new Currency(['code' => 'EUR']);

        $invoice = new Invoice([
            'number' => '0005-0858',
            'date' => '2026-06-04',
            'balance' => 98.0,
            'currency_id' => 978,
        ]);
        $invoice->setRelation('enterprise', $enterprise);
        $invoice->setRelation('currency', $currency);

        $label = PaymentInvoiceLinkOptionFormatter::label($invoice);

        $this->assertSame('0005-0858 — 04/06/2026 — Diaz Williams — 98,00 EUR', $label);
    }
}
