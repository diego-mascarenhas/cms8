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
}
