<?php

namespace Tests\Unit;

use App\Services\Fiscal\Cuentica\CuenticaInboundPayloadNormalizer;
use Tests\TestCase;

class CuenticaInboundPayloadNormalizerTest extends TestCase
{
    public function test_normalizes_nested_cuentica_sale_payload(): void
    {
        $normalizer = new CuenticaInboundPayloadNormalizer;

        $normalized = $normalizer->normalizeSale([
            'id' => 3518861,
            'date' => '2026-06-13',
            'issued' => true,
            'invoice_serie' => 'generic',
            'invoice_number' => 1,
            'customer' => [
                'id' => 1335227,
                'tax_id' => '30717401847',
                'business_name' => 'CIQ S.A.',
                'email' => 'dp@diegopons.com.ar',
                'country_code' => 'AR',
            ],
            'amount_details' => [
                'total_base' => 30,
                'total_invoice' => 36.3,
            ],
            'charges' => [
                ['amount' => 36.3, 'paid' => false],
            ],
        ]);

        $this->assertSame(30.0, $normalized['total_base']);
        $this->assertSame(36.3, $normalized['total_invoice']);
        $this->assertSame('30717401847', $normalized['customer_tax_id']);
        $this->assertSame('CIQ S.A.', $normalized['customer_name']);
        $this->assertSame('generic-1', $normalized['number']);
    }

    public function test_normalizes_scalar_provider_id_on_purchase_payload(): void
    {
        $normalizer = new CuenticaInboundPayloadNormalizer;

        $normalized = $normalizer->normalizePurchase([
            'id' => 303,
            'provider' => 888,
            'document_number' => 'C-0009',
            'amount_details' => [
                'total_base' => 40,
                'total_expense' => 48.4,
            ],
        ]);

        $this->assertSame(888, $normalized['provider']['id']);
        $this->assertSame(40.0, $normalized['total_base']);
        $this->assertSame(48.4, $normalized['total_expense']);
    }
}
