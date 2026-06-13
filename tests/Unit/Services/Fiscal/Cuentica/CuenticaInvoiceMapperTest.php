<?php

namespace Tests\Unit\Services\Fiscal\Cuentica;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Services\Fiscal\Cuentica\CuenticaInvoiceMapper;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CuenticaInvoiceMapperTest extends TestCase
{
    private CuenticaInvoiceMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new CuenticaInvoiceMapper;

        config([
            'fiscal.platforms.cuentica.default_tax_percent' => 21,
            'fiscal.platforms.cuentica.default_sell_type' => 'service',
            'fiscal.platforms.cuentica.default_tax_regime' => '01',
            'fiscal.platforms.cuentica.default_tax_subjection_code' => 'S1',
            'fiscal.platforms.cuentica.default_payment_method' => 'card',
            'fiscal.platforms.cuentica.invoice_serie' => null,
        ]);
    }

    public function test_maps_invoice_with_items_to_cuentica_payload(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-2026-0001',
            'date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_abc123',
        ]);

        $invoice->setRelation('items', new Collection([
            new InvoiceItem([
                'description' => 'Plan Pro',
                'quantity' => 1,
                'unit_price' => 100,
                'discount' => 0,
                'tax_percentage' => 21,
            ]),
        ]));
        $invoice->setRelation('payments', new Collection);

        $payload = $this->mapper->map($invoice, 555);

        $this->assertTrue($payload['issued']);
        $this->assertSame(555, $payload['customer']);
        $this->assertSame('2026-06-01', $payload['date']);
        $this->assertCount(1, $payload['invoice_lines']);
        $this->assertSame('Plan Pro', $payload['invoice_lines'][0]['concept']);
        $this->assertSame(100.0, $payload['invoice_lines'][0]['amount']);
        $this->assertSame(21.0, $payload['invoice_lines'][0]['tax']);
        $this->assertSame('service', $payload['invoice_lines'][0]['sell_type']);

        $this->assertCount(1, $payload['charges']);
        $this->assertSame(121.0, $payload['charges'][0]['amount']);
        $this->assertTrue($payload['charges'][0]['paid']);
        $this->assertSame('card', $payload['charges'][0]['payment_method']);

        $this->assertContains('stripe', $payload['tags']);
        $this->assertContains('in_abc123', $payload['tags']);
    }

    public function test_falls_back_to_single_line_when_no_items(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-2026-0002',
            'date' => '2026-06-02',
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 242,
            'balance' => 242,
            'status' => 2,
        ]);

        $invoice->setRelation('items', new Collection);
        $invoice->setRelation('payments', new Collection);

        $payload = $this->mapper->map($invoice, 7);

        $this->assertCount(1, $payload['invoice_lines']);
        $this->assertSame(200.0, $payload['invoice_lines'][0]['amount']);
        $this->assertSame(21.0, $payload['invoice_lines'][0]['tax']);
        $this->assertFalse($payload['charges'][0]['paid']);
    }

    public function test_uses_latest_payment_date_for_charge(): void
    {
        $invoice = $this->makeInvoice([
            'number' => 'INV-2026-0003',
            'date' => '2026-06-01',
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 2,
        ]);

        $invoice->setRelation('items', new Collection);
        $invoice->setRelation('payments', new Collection([
            new Payment(['date' => '2026-06-05', 'amount' => 60.5]),
        ]));

        $payload = $this->mapper->map($invoice, 1);

        $this->assertSame('2026-06-05', $payload['charges'][0]['date']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeInvoice(array $attributes): Invoice
    {
        $invoice = new Invoice;
        $invoice->forceFill($attributes);

        return $invoice;
    }
}
