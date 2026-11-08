<?php

namespace Tests\Unit\Models;

use App\Models\InvoiceSync;
use Tests\TestCase;

class InvoiceSyncConsumptionLinesTest extends TestCase
{
    public function test_usage_lines_split_into_name_period_and_detail(): void
    {
        $invoice = new InvoiceSync([
            'number' => '0005-9001',
            'raw_payload' => [
                'lines' => [
                    'data' => [
                        ['description' => 'Almacenamiento · 24 de septiembre al 24 de octubre 2026 · 11,7 MB'],
                        ['description' => 'Envíos WhatsApp · 24 de septiembre al 24 de octubre 2026 · 7.625 envíos'],
                        ['description' => 'Tokens IA · Agosto 2026'],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            [
                'name' => 'Almacenamiento',
                'period' => '24 de septiembre al 24 de octubre 2026',
                'detail' => '11,7 MB',
            ],
            [
                'name' => 'Envíos WhatsApp',
                'period' => '24 de septiembre al 24 de octubre 2026',
                'detail' => '7.625 envíos',
            ],
            [
                'name' => 'Tokens IA',
                'period' => 'Agosto 2026',
                'detail' => '',
            ],
        ], $invoice->consumptionLines());

        $this->assertSame(
            'Almacenamiento · 24 de septiembre al 24 de octubre 2026 · 11,7 MB · Envíos WhatsApp · 24 de septiembre al 24 de octubre 2026 · 7.625 envíos · Tokens IA · Agosto 2026',
            $invoice->consumptionSummary(),
        );
    }

    public function test_invoice_without_lines_falls_back_to_the_number(): void
    {
        $invoice = new InvoiceSync([
            'number' => '0005-9001',
            'raw_payload' => [],
        ]);

        $this->assertSame([
            ['name' => '0005-9001', 'period' => '', 'detail' => ''],
        ], $invoice->consumptionLines());
        $this->assertSame('0005-9001', $invoice->consumptionSummary());
    }
}
