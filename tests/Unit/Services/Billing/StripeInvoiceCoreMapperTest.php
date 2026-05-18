<?php

namespace Tests\Unit\Services\Billing;

use App\Models\InvoiceSync;
use App\Services\Billing\StripeInvoiceCoreMapper;
use PHPUnit\Framework\TestCase;

class StripeInvoiceCoreMapperTest extends TestCase
{
    private StripeInvoiceCoreMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new StripeInvoiceCoreMapper;
    }

    public function test_maps_stripe_status_to_humano_status(): void
    {
        $this->assertSame(9, $this->mapper->mapStatus('draft'));
        $this->assertSame(1, $this->mapper->mapStatus('open'));
        $this->assertSame(2, $this->mapper->mapStatus('paid'));
        $this->assertSame(3, $this->mapper->mapStatus('void'));
        $this->assertSame(7, $this->mapper->mapStatus('uncollectible'));
        $this->assertSame(7, $this->mapper->mapStatus('unknown'));
    }

    public function test_paid_flag_upgrades_open_status(): void
    {
        $this->assertSame(2, $this->mapper->mapStatus('open', true));
    }

    public function test_paid_and_void_status_force_zero_balance(): void
    {
        $this->assertSame(0.0, $this->mapper->mapBalance('paid', 98, 98));
        $this->assertSame(0.0, $this->mapper->mapBalance('void', 98, 98));
        $this->assertSame(0.0, $this->mapper->mapBalance('open', 98, 98, null, true));
    }

    public function test_open_status_uses_amount_remaining(): void
    {
        $this->assertSame(98.0, $this->mapper->mapBalance('open', 98, 98));
    }

    public function test_open_status_derives_balance_from_total_minus_paid_when_remaining_missing(): void
    {
        $this->assertSame(25.0, $this->mapper->mapBalance('open', null, 100, 75));
    }

    public function test_uncollectible_keeps_remaining_balance(): void
    {
        $this->assertSame(98.0, $this->mapper->mapBalance('uncollectible', 98, 98));
    }

    public function test_map_from_invoice_sync_row(): void
    {
        $row = new InvoiceSync([
            'status' => 'void',
            'amount_remaining' => 98,
            'total' => 98,
            'paid' => false,
        ]);

        $mapped = $this->mapper->mapFromInvoiceSync($row);

        $this->assertSame(3, $mapped['status']);
        $this->assertSame(0.0, $mapped['balance']);
    }
}
