<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillStripeInvoiceFiscalDatesTest extends TestCase
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

    public function test_backfill_updates_date_from_finalized_at(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
            'code' => 'cus_backfill',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_backfill_001',
            'customer_id' => 'cus_backfill',
            'number' => '0005-0857',
            'status' => 'paid',
            'currency' => 'eur',
            'total' => 100,
            'paid' => true,
            'invoice_created_at' => '2026-05-21 10:00:00',
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-06-04 15:00:00'),
                ],
            ],
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0857',
            'date' => '2026-05-21',
            'gross_amount' => 100,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_backfill_001',
        ]);

        $this->artisan('invoices:backfill-stripe-fiscal-dates', ['--team' => $team->id])
            ->assertSuccessful();

        $invoice->refresh();
        $this->assertSame('2026-06-04', Carbon::parse($invoice->date)->toDateString());
    }
}
