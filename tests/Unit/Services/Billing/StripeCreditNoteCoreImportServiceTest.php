<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Team;
use App\Services\Billing\StripeCreditNoteCoreImportService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCreditNoteCoreImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_imports_credit_note_as_separate_invoice_without_touching_original(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente ES',
            'country' => 'ES',
        ]);

        $original = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0989',
            'date' => '2026-05-01',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_original_001',
        ]);

        $abono = app(StripeCreditNoteCoreImportService::class)->importFromStripePayload(
            (int) $team->id,
            [
                'id' => 'cn_abono_001',
                'number' => '0005-0990',
                'status' => 'issued',
                'created' => strtotime('2026-05-10 12:00:00'),
                'subtotal' => 10000,
                'total' => 12100,
                'amount' => 12100,
                'tax' => 2100,
                'currency' => 'eur',
                'memo' => 'Abono factura 0005-0989',
                'lines' => [
                    'data' => [
                        [
                            'description' => 'Devolución servicio',
                            'quantity' => 1,
                            'amount_excluding_tax' => 10000,
                            'amount' => 10000,
                            'tax_amounts' => [
                                ['amount' => 2100],
                            ],
                        ],
                    ],
                ],
            ],
            $original,
        );

        $this->assertInstanceOf(Invoice::class, $abono);
        $this->assertSame('0005-0990', $abono->number);
        $this->assertSame(2, (int) $abono->type_id);
        $this->assertSame(4, (int) $abono->status);
        $this->assertSame('cn_abono_001', $abono->source_reference_id);
        $this->assertSame(100.0, (float) $abono->gross_amount);
        $this->assertSame(121.0, (float) $abono->total_amount);
        $this->assertTrue($abono->isCreditNote());
        $this->assertSame(21.0, (float) $abono->items->first()->tax_percentage);

        $original->refresh();
        $this->assertSame(100.0, (float) $original->gross_amount);
        $this->assertSame(121.0, (float) $original->total_amount);
        $this->assertSame('0005-0989', $original->number);
        $this->assertSame(2, (int) $original->status);
    }
}
