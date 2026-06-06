<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
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
                        'price' => ['unit_amount' => 9600],
                        'discount_amounts' => [['amount' => 7680]],
                    ]],
                ],
            ],
            'last_synced_at' => now(),
        ]);

        $items = $this->service->forInvoice($invoice);

        $this->assertCount(1, $items);
        $this->assertSame('2 × VPS Essential (at €96.00 / month)', $items->first()['description']);
        $this->assertSame(2.0, $items->first()['quantity']);
        $this->assertSame(96.0, $items->first()['unit_price']);
        $this->assertSame(76.8, $items->first()['discount']);
        $this->assertSame(115.2, $items->first()['total']);
    }
}
