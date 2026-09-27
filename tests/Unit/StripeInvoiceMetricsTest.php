<?php

namespace Tests\Unit;

use App\Support\StripeInvoiceMetrics;
use PHPUnit\Framework\TestCase;

class StripeInvoiceMetricsTest extends TestCase
{
    public function test_returns_default_when_no_invoices(): void
    {
        $this->assertSame('EUR', StripeInvoiceMetrics::displayCurrencyForInvoices([]));
    }

    public function test_returns_uppercase_currency_from_first_invoice(): void
    {
        $invoices = [(object) ['currency' => 'ars']];

        $this->assertSame('ARS', StripeInvoiceMetrics::displayCurrencyForInvoices($invoices));
    }

    public function test_custom_default_currency(): void
    {
        $this->assertSame('USD', StripeInvoiceMetrics::displayCurrencyForInvoices([], 'USD'));
    }

    public function test_display_currency_prefers_unpaid_invoices(): void
    {
        $paid = [(object) ['currency' => 'eur']];
        $open = [(object) ['currency' => 'ars']];
        $uncollectible = [];

        $this->assertSame(
            'ARS',
            StripeInvoiceMetrics::displayCurrencyForStripeInvoiceGroups($paid, $open, $uncollectible),
        );
    }

    public function test_argentina_contact_prefers_ars_when_present_in_any_invoice(): void
    {
        $paid = [(object) ['currency' => 'eur']];
        $open = [];
        $uncollectible = [];

        $this->assertSame(
            'EUR',
            StripeInvoiceMetrics::displayCurrencyForStripeInvoiceGroups($paid, $open, $uncollectible, 'EUR', 'ar'),
        );

        $paidBoth = [(object) ['currency' => 'eur'], (object) ['currency' => 'ars']];

        $this->assertSame(
            'ARS',
            StripeInvoiceMetrics::displayCurrencyForStripeInvoiceGroups($paidBoth, [], [], 'EUR', 'ar'),
        );
    }

    public function test_sum_amounts_by_currency_groups_rows(): void
    {
        $rows = [
            ['amount' => 100.5, 'currency' => 'ars'],
            ['amount' => 50, 'currency' => 'ARS'],
            ['amount' => 10, 'currency' => 'eur'],
        ];

        $this->assertEqualsWithDelta(
            ['ARS' => 150.5, 'EUR' => 10.0],
            StripeInvoiceMetrics::sumAmountsByCurrency($rows),
            0.001,
        );
    }

    public function test_format_metric_totals_single_and_multi_currency(): void
    {
        $this->assertSame('0.00', StripeInvoiceMetrics::formatMetricTotalsForDisplay([]));

        $this->assertSame(
            '62,727.27 ARS',
            StripeInvoiceMetrics::formatMetricTotalsForDisplay(['ARS' => 62727.27]),
        );

        $this->assertSame(
            '100.00 ARS · 10.00 EUR',
            StripeInvoiceMetrics::formatMetricTotalsForDisplay(['ARS' => 100, 'EUR' => 10]),
        );
    }

    public function test_format_with_primary_skips_equivalent_when_only_primary_currency(): void
    {
        $this->assertSame(
            '10.00 EUR',
            StripeInvoiceMetrics::formatMetricTotalsWithPrimaryEquivalent(['EUR' => 10], 'EUR'),
        );
    }

    public function test_euro_balance_does_not_append_another_currency(): void
    {
        $metrics = StripeInvoiceMetrics::contactBalanceMetrics(
            [
                ['amount' => 156.65, 'currency' => 'EUR'],
            ],
            [],
            'EUR',
        );

        $this->assertSame('156.65 EUR', $metrics['total_paid']);
        $this->assertStringNotContainsString('≈', $metrics['total_paid']);
    }

    public function test_mixed_currency_balance_card_shows_only_euros(): void
    {
        $metrics = StripeInvoiceMetrics::contactBalanceMetrics(
            [
                ['amount' => 215107.68, 'currency' => 'ARS'],
                ['amount' => 21.99, 'currency' => 'EUR'],
            ],
            [
                ['amount' => 1000, 'currency' => 'ARS'],
            ],
            'ARS',
        );

        $this->assertSame('21.99 EUR', $metrics['total_paid']);
        $this->assertSame('0.00 EUR', $metrics['unpaid']);
    }

    public function test_sum_amounts_converted_to_same_target_adds_amounts_without_exchange_table(): void
    {
        $this->assertSame(15.5, StripeInvoiceMetrics::sumAmountsConvertedToCurrency(['EUR' => 15.5], 'EUR'));
    }

    public function test_stripe_paid_invoice_amount_falls_back_to_total_when_amount_paid_is_zero(): void
    {
        $invoice = (object) [
            'amount_paid' => 0,
            'total' => 7377900,
        ];

        $this->assertSame(73779.0, StripeInvoiceMetrics::stripePaidInvoiceAmount($invoice));
    }

    public function test_contact_balance_metrics_sums_visible_invoice_rows(): void
    {
        $metrics = StripeInvoiceMetrics::contactBalanceMetrics(
            [
                ['amount' => 73779, 'currency' => 'ARS'],
                ['amount' => 73779, 'currency' => 'ARS'],
            ],
            [
                ['amount' => 73779, 'currency' => 'ARS'],
            ],
            'ARS',
        );

        $this->assertSame('0.00 EUR', $metrics['total_paid']);
        $this->assertSame('0.00 EUR', $metrics['unpaid']);
        $this->assertEqualsWithDelta(147558.0, $metrics['total_paid_raw'], 0.001);
        $this->assertEqualsWithDelta(73779.0, $metrics['unpaid_raw'], 0.001);
    }

    public function test_metric_card_display_falls_back_to_table_rows_when_metrics_missing(): void
    {
        $rows = [
            ['amount' => 215107.68, 'currency' => 'ARS'],
            ['amount' => 21.99, 'currency' => 'EUR'],
        ];

        $this->assertSame(
            '21.99 EUR',
            StripeInvoiceMetrics::metricCardDisplay(null, 'total_paid', $rows),
        );

        $this->assertSame(
            '21.99 EUR',
            StripeInvoiceMetrics::metricCardDisplay(['total_paid' => '0.00'], 'total_paid', $rows),
        );
    }
}
