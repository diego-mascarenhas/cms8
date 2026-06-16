<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\SubscriptionProduct;
use App\Models\User;
use App\Services\Finance\InvoiceDisplayLineItemService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDisplayLineItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceDisplayLineItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $this->service = app(InvoiceDisplayLineItemService::class);
    }

    public function test_it_builds_line_items_from_stripe_invoice_sync(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0682',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 192,
            'discount' => 76.8,
            'total_amount' => 115.2,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_invoice',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_test_invoice',
            'status' => 'paid',
            'currency' => 'EUR',
            'total' => 115.2,
            'raw_payload' => [
                'lines' => [
                    'data' => [[
                        'description' => '2 × VPS Essential (at €96.00 / month)',
                        'quantity' => 2,
                        'amount' => 19200,
                        'price' => [
                            'id' => 'price_vps_essential',
                            'unit_amount' => 9600,
                            'product' => 'prod_vps_essential',
                        ],
                        'discount_amounts' => [['amount' => 7680]],
                    ]],
                ],
            ],
            'last_synced_at' => now(),
        ]);

        SubscriptionProduct::query()->create([
            'stripe_product' => 'prod_vps_essential',
            'stripe_price' => 'price_vps_essential',
            'name' => 'VPS Essential',
            'category' => 'vps',
            'active' => true,
            'currency' => 'eur',
        ]);

        $items = $this->service->forInvoice($invoice);

        $this->assertCount(1, $items);
        $this->assertSame('2 × VPS Essential (at €96.00 / month)', $items->first()['description']);
        $this->assertSame(__('VPS'), $items->first()['category']);
        $this->assertSame(2.0, $items->first()['quantity']);
        $this->assertSame(96.0, $items->first()['unit_price']);
        $this->assertSame(76.8, $items->first()['discount']);
        $this->assertSame(115.2, $items->first()['total']);
    }

    public function test_it_builds_line_items_from_cuentica_invoice_sync_when_no_persisted_items(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'CIQ S.A.',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'generic-1',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 30,
            'discount' => 0,
            'total_amount' => 36.3,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'cuentica',
            'source_reference_id' => 'sale:3518861',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:3518861',
            'status' => 'paid',
            'currency' => 'EUR',
            'total' => 36.3,
            'raw_payload' => [
                'invoice_lines' => [[
                    'concept' => 'Servicio mensual',
                    'quantity' => 1,
                    'amount' => 30,
                    'discount' => 0,
                    'tax' => 21,
                ]],
            ],
            'last_synced_at' => now(),
        ]);

        $items = $this->service->forInvoice($invoice);

        $this->assertCount(1, $items);
        $this->assertSame('Servicio mensual', $items->first()['description']);
        $this->assertSame(1.0, $items->first()['quantity']);
        $this->assertSame(30.0, $items->first()['unit_price']);
        $this->assertSame(30.0, $items->first()['total']);
    }
}
