<?php

namespace Tests\Unit\Support;

use App\Support\PaymentTableAmountFormatter;
use Tests\TestCase;

class PaymentTableAmountFormatterTest extends TestCase
{
    public function test_format_shows_amount_with_currency_code(): void
    {
        $html = PaymentTableAmountFormatter::format(10608.16, 'ARS');

        $this->assertStringContainsString('fw-medium', $html);
        $this->assertStringContainsString('10.608,16 ARS', $html);
    }

    public function test_format_expense_shows_negative_amount_with_currency_code(): void
    {
        $html = PaymentTableAmountFormatter::formatExpense(210000.0, 'ARS');

        $this->assertStringContainsString('text-danger fw-bold', $html);
        $this->assertStringContainsString('- 210.000,00 ARS', $html);
    }
}
