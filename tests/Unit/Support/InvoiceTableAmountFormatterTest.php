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
        $this->assertStringContainsString('98,00 EUR', $html);
        $this->assertStringNotContainsString('€', $html);
        $this->assertStringNotContainsString('≈', $html);
    }

    public function test_format_native_uses_spanish_decimal_separator_for_large_amounts(): void
    {
        $html = InvoiceTableAmountFormatter::formatNative(15918.88, 'ARS');

        $this->assertStringContainsString('15.918,88 ARS', $html);
    }
}
