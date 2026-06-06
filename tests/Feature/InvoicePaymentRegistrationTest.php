<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
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

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-ars',
            'name' => 'Banco ARS',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create(['name' => 'Transferencia']);

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
                'type_id' => $type->id,
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

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-ars',
            'name' => 'Banco ARS',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create(['name' => 'Transferencia']);

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

        $this->actingAs($member)
            ->post(route('invoice.payments.store', $invoice), [
                'amount' => 50,
                'date' => now()->toDateString(),
                'account_id' => $account->id,
                'type_id' => $type->id,
            ])
            ->assertForbidden();
    }

    public function test_invoice_show_displays_payment_form_only_for_team_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $owner->assignRole('admin');
        $team = $owner->ownedTeams()->first();

        $owner->forceFill(['current_team_id' => $team->id])->save();

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-ars',
            'name' => 'Banco ARS',
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
            ->assertSee(__('invoice_payment.register_title'), false);

        $this->actingAs($member)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertDontSee(__('invoice_payment.register_title'), false);
    }
}
