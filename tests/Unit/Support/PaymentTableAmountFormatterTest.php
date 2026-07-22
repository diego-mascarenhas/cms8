<?php

namespace Tests\Unit\Support;

use App\Models\ExchangeRate;
use App\Support\PaymentTableAmountFormatter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTableAmountFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_shows_amount_with_currency_code(): void
    {
        $html = PaymentTableAmountFormatter::format(10608.16, 'ARS');

        $this->assertStringContainsString('fw-medium', $html);
        $this->assertStringContainsString('10.608,16 ARS', $html);
    }

    public function test_format_income_shows_amount_with_currency_without_plus_sign(): void
    {
        $html = PaymentTableAmountFormatter::formatIncome(9547.34, 'EUR');

        $this->assertStringContainsString('text-success fw-bold', $html);
        $this->assertStringContainsString('9.547,34 EUR', $html);
        $this->assertStringNotContainsString('+', $html);
    }

    public function test_format_expense_shows_amount_with_currency_without_minus_sign(): void
    {
        $html = PaymentTableAmountFormatter::formatExpense(210000.0, 'ARS');

        $this->assertStringContainsString('text-danger fw-bold', $html);
        $this->assertStringContainsString('210.000,00 ARS', $html);
        $this->assertStringNotContainsString('- ', $html);
    }

    public function test_format_converted_shows_reporting_amount_native_and_rate(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.90,
            'date' => '2024-05-01',
            'fetched_at' => now(),
        ]);

        $html = PaymentTableAmountFormatter::formatConverted(
            100.0,
            'USD',
            'EUR',
            Carbon::parse('2024-05-12'),
            'text-success fw-bold',
        );

        $this->assertStringContainsString('90,00 EUR', $html);
        $this->assertStringContainsString('100,00 USD', $html);
        $this->assertStringContainsString('1,1111', $html);
    }
}
