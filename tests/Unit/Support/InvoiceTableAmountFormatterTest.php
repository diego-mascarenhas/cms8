<?php

namespace Tests\Unit\Support;

use App\Models\ExchangeRate;
use App\Support\InvoiceTableAmountFormatter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTableAmountFormatterTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_format_with_reporting_keeps_native_and_shows_team_conversion(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'ARS',
            'target_currency' => 'EUR',
            'rate' => 0.001,
            'date' => '2026-07-01',
            'fetched_at' => now(),
        ]);

        $html = InvoiceTableAmountFormatter::formatWithReporting(
            10000.0,
            'ARS',
            'EUR',
            Carbon::parse('2026-07-22'),
            'h6 fw-medium',
        );

        $this->assertStringContainsString('10.000,00 ARS', $html);
        $this->assertStringContainsString('10,00 EUR', $html);
        $this->assertStringContainsString('≈', $html);
        $this->assertStringContainsString('1.000,0000', $html);
    }

    public function test_format_with_reporting_skips_conversion_when_currencies_match(): void
    {
        $html = InvoiceTableAmountFormatter::formatWithReporting(100.0, 'EUR', 'EUR');

        $this->assertStringContainsString('100,00 EUR', $html);
        $this->assertStringNotContainsString('≈', $html);
    }

    public function test_reporting_conversion_note_describes_exchange_rate(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'ARS',
            'target_currency' => 'EUR',
            'rate' => 0.001,
            'date' => '2026-07-01',
            'fetched_at' => now(),
        ]);

        $note = InvoiceTableAmountFormatter::reportingConversionNote(
            'ARS',
            'EUR',
            Carbon::parse('2026-07-22'),
        );

        $this->assertSame(
            __('Exchange rate: 1 :reporting = :rate :native', [
                'reporting' => 'EUR',
                'native' => 'ARS',
                'rate' => '1.000,0000',
            ]),
            $note,
        );
    }

    public function test_reporting_conversion_note_is_null_when_currencies_match(): void
    {
        $this->assertNull(InvoiceTableAmountFormatter::reportingConversionNote('EUR', 'EUR'));
    }
}
