<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\User;
use App\Services\Finance\InvoiceCurrencyService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceCurrencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
        $this->service = app(InvoiceCurrencyService::class);
    }

    public function test_legacy_moneda_maps_ars_to_currency_id_32(): void
    {
        $this->assertSame(32, $this->service->legacyMonedaIdToCurrencyId(1));
        $this->assertSame(840, $this->service->legacyMonedaIdToCurrencyId(2));
        $this->assertSame(978, $this->service->legacyMonedaIdToCurrencyId(3));
    }

    public function test_resync_sets_currency_id_from_stripe_invoice_sync(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        InvoiceSync::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_usd_test',
            'number' => 'STR-USD',
            'status' => 'paid',
            'currency' => 'usd',
            'amount_due' => 100,
            'total' => 100,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'STR-USD',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_usd_test',
        ]);

        $stats = $this->service->resync(teamId: $team->id, fromLegacy: false, manualDefault: false);

        $this->assertSame(1, $stats['stripe']);
        $this->assertSame(840, (int) $invoice->fresh()->currency_id);
        $this->assertSame('USD', $invoice->fresh(['currency'])->currency_code);
    }

    public function test_invoice_currency_code_uses_related_currency(): void
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
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-ARS',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
            'currency_id' => Currency::query()->where('code', 'ARS')->value('id'),
        ]);

        $this->assertSame('ARS', $invoice->fresh(['currency'])->currency_code);
    }

    public function test_resync_sets_manual_invoices_to_ars_when_currency_id_is_missing(): void
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
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0001-00000102',
            'date' => now()->toDateString(),
            'due_date' => null,
            'gross_amount' => 210,
            'discount' => 0,
            'total_amount' => 210,
            'balance' => 210,
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        $stats = $this->service->resync(teamId: $team->id, fromLegacy: false);

        $this->assertSame(1, $stats['manual_default']);
        $this->assertSame(32, (int) $invoice->fresh()->currency_id);
        $this->assertSame('ARS', $invoice->fresh(['currency'])->currency_code);
    }
}
