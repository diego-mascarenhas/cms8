<?php

namespace Tests\Unit\Support;

use App\Support\InvoiceTableAmountFormatter;
use Tests\TestCase;

class InvoiceTableAmountFormatterTest extends TestCase
{
    public function test_format_native_shows_exact_amount_with_currency_code(): void
    {
        $html = InvoiceTableAmountFormatter::formatNative(98.0, 'EUR');

        $this->assertStringContainsString('fw-bold', $html);
        $this->assertStringContainsString('98.00 EUR', $html);
        $this->assertStringNotContainsString('€', $html);
        $this->assertStringNotContainsString('≈', $html);
    }
}
