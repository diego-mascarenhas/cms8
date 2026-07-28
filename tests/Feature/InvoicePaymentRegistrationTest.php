<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoicePaymentRegistrationTest extends TestCase
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
            CurrencySeeder::class,
        ]);
    }

    public function test_team_owner_can_register_payment_from_invoice_show(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $this->seed([\Database\Seeders\PaymentTypeSeeder::class]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'cash-ars',
            'name' => 'Efectivo',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('invoice.payments.store', $invoice), [
                'amount' => 100,
                'date' => now()->toDateString(),
                'account_id' => $account->id,
                'type_id' => 1,
            ])
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('success');

        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'account_id' => $account->id,
        ]);
    }

    public function test_non_owner_cannot_register_payment_from_invoice_show(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();

        $member = User::factory()->create();
        $member->assignRole('admin');
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->seed([\Database\Seeders\PaymentTypeSeeder::class]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'cash-ars',
            'name' => 'Efectivo',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-002',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
        ]);

        $response = $this->actingAs($member)
            ->from(route('invoice.show', $invoice->id))
            ->post(route('invoice.payments.store', $invoice), [
                'amount' => 50,
                'date' => now()->toDateString(),
                'account_id' => $account->id,
                'type_id' => 1,
            ]);

        $this->assertTrue(
            in_array($response->status(), [403, 302], true),
            'Expected forbidden or redirect without creating payment, got '.$response->status(),
        );
        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 50,
        ]);
    }

    public function test_invoice_show_displays_payment_forms_only_for_team_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();

        $owner->forceFill(['current_team_id' => $team->id])->save();

        $this->seed([\Database\Seeders\PaymentTypeSeeder::class]);

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'cash-ars',
            'name' => 'Efectivo',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-003',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 80,
            'discount' => 0,
            'total_amount' => 80,
            'balance' => 80,
            'status' => 2,
        ]);

        $member = User::factory()->create();
        $member->assignRole('admin');
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_payment.electronic_title'), false)
            ->assertSee(__('invoice_payment.register_title'), false);

        $this->actingAs($member)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertDontSee(__('invoice_payment.electronic_title'), false)
            ->assertDontSee(__('invoice_payment.register_title'), false);
    }

    public function test_invoice_show_hides_electronic_payment_form_when_balance_is_zero(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $team->id])->save();

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Caja Rural',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

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
            'number' => 'F-004',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $this->actingAs($owner)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertDontSee(__('invoice_payment.electronic_title'), false)
            ->assertDontSee(__('invoice_payment.register_title'), false);
    }

    public function test_team_owner_can_link_mercadopago_sync_from_invoice_show(): void
    {
        $this->seed([\Database\Seeders\PaymentTypeSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
            'code' => 'cus_test_electronic',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-010',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 1500.50,
            'discount' => 0,
            'total_amount' => 1500.50,
            'balance' => 1500.50,
            'status' => 1,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_electronic_link_1',
        ]);

        $sync = \App\Models\PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'mp-electronic-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
            'raw_payload' => [
                'transaction_details' => [
                    'transaction_id' => 'REF-ELECTRONIC-1',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee('mp-electronic-1', false);

        $this->actingAs($user)
            ->post(route('invoice.electronic-payments.store', $invoice), [
                'payment_sync_id' => $sync->id,
            ])
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'source_provider' => 'mercadopago',
            'source_reference_id' => 'mp-electronic-1',
        ]);
    }
}
