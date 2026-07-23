<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MercadoPagoPaymentSyncAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_admin_can_list_pending_mercadopago_syncs(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-pending-1',
            'customer_id' => '111',
            'customer_email' => 'payer@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.index'))
            ->assertOk()
            ->assertSee('mp-pending-1', false)
            ->assertSee('Bank Transfer', false);
    }

    public function test_admin_can_import_sync_with_forced_enterprise_and_invoice(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Transfer',
            'code' => null,
            'email' => 'cliente@example.com',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1000',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 1500.50,
            'discount' => 0,
            'total_amount' => 1500.50,
            'balance' => 1500.50,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-force-1',
            'customer_id' => '999888',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Bank Transfer',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoice->id],
                'remarks' => '0005-0950',
                'link_payer_code' => 1,
            ])
            ->assertRedirect(route('payments.index'));

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'mp-force-1')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($enterprise->id, (int) $payment->enterprise_id);
        $this->assertSame($invoice->id, (int) $payment->invoice_id);
        $this->assertSame(12, (int) $payment->type_id);
        $this->assertSame('0005-0950', $payment->remarks);
        $this->assertSame('999888', $enterprise->fresh()->code);
    }

    public function test_assign_materializes_open_stripe_invoice_sync_for_client(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => '301',
            'code' => 'cus_TDu3TWcwCTek5O',
            'email' => 'info@trescientosuno.com',
        ]);

        \App\Models\InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_1TvCA8RwN51ygFdebsmbYyGd',
            'customer_id' => 'cus_TDu3TWcwCTek5O',
            'customer_email' => 'info@trescientosuno.com',
            'number' => '0005-0957',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 24.00,
            'amount_paid' => 0,
            'amount_remaining' => 24.00,
            'subtotal' => 19.83,
            'total' => 24.00,
            'paid' => false,
            'invoice_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-open-stripe',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 1060816,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 1060816,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', [
                'sync' => $sync,
                'enterprise_id' => $enterprise->id,
            ]))
            ->assertOk()
            ->assertSee('0005-0957', false)
            ->assertDontSee(__('payment_sync.mercadopago.no_open_invoices'), false);

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_1TvCA8RwN51ygFdebsmbYyGd',
            'number' => '0005-0957',
        ]);

        $local = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'in_1TvCA8RwN51ygFdebsmbYyGd')
            ->first();

        $this->assertNotNull($local);
        $this->assertGreaterThan(0, (float) $local->balance);
    }

    public function test_admin_can_import_split_across_two_invoices(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Combo',
            'email' => 'combo@example.com',
        ]);

        $invoiceA = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-3001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 1000,
            'discount' => 0,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 1,
        ]);
        $invoiceB = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-3002',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 500,
            'discount' => 0,
            'total_amount' => 500,
            'balance' => 500,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-split-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->get(route('payments.syncs.mercadopago.assign', ['sync' => $sync, 'enterprise_id' => $enterprise->id]))
            ->assertOk()
            ->assertSee('Sugerencias por importe', false)
            ->assertSee('Suma de facturas', false);

        $this->actingAs($user)
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterprise->id,
                'invoice_ids' => [$invoiceA->id, $invoiceB->id],
            ])
            ->assertRedirect(route('payments.index'));

        $this->assertSame(2, Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'mercadopago')
            ->where('source_reference_id', 'like', 'mp-split-1:%')
            ->count());
    }

    public function test_import_rejects_invoice_from_other_enterprise(): void
    {
        [$user, $team] = $this->makeAdminWithTeam();

        $enterpriseA = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente A',
            'email' => 'a@example.com',
        ]);
        $enterpriseB = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente B',
            'email' => 'b@example.com',
        ]);

        $invoiceB = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterpriseB->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-2000',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-mismatch',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($user)
            ->from(route('payments.syncs.mercadopago.assign', $sync))
            ->post(route('payments.syncs.mercadopago.import', $sync), [
                'enterprise_id' => $enterpriseA->id,
                'invoice_ids' => [$invoiceB->id],
            ])
            ->assertRedirect(route('payments.syncs.mercadopago.assign', $sync))
            ->assertSessionHasErrors('invoice_ids');

        $this->assertFalse(
            Payment::withoutGlobalScopes()
                ->where('source_reference_id', 'mp-mismatch')
                ->exists(),
        );
    }

    /**
     * @return array{0: User, 1: \App\Models\Team}
     */
    private function makeAdminWithTeam(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }
}
