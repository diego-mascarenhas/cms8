<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoicePaymentResyncTest extends TestCase
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

    public function test_team_owner_can_resync_a_linked_but_unfinalized_payment(): void
    {
        [$user, $invoice] = $this->makeUserInvoiceAndUnfinalizedPayment();

        $this->assertSame(100.0, (float) $invoice->balance);

        $this->actingAs($user)
            ->post(route('invoice.resync-payment', $invoice))
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('warning');

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(2, $invoice->status);
    }

    public function test_show_page_displays_resync_button_for_team_owner_with_mercadopago_payment(): void
    {
        [$user, $invoice] = $this->makeUserInvoiceAndUnfinalizedPayment();

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee(__('invoice_payment.resync_button'), false)
            ->assertSee(route('invoice.resync-payment', $invoice), false);
    }

    public function test_button_endpoint_forbidden_for_non_owner(): void
    {
        [$owner, $invoice] = $this->makeUserInvoiceAndUnfinalizedPayment();
        $team = $owner->ownedTeams()->first();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($member)
            ->post(route('invoice.resync-payment', $invoice))
            ->assertRedirect('/misc-not-authorized');

        $this->assertSame(100.0, (float) $invoice->fresh()->balance);
    }

    public function test_resync_reports_when_no_mercadopago_payment_is_linked(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Acme SL',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-002',
            'date' => now()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('invoice.resync-payment', $invoice))
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('error');
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function makeUserInvoiceAndUnfinalizedPayment(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Acme SL',
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp',
            'name' => 'Mercado Pago',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 1,
        ]);

        // Simulates the bug: a payment already linked to the invoice (invoice_id
        // set) that never went through finalizeLinkedInvoice, so the invoice
        // balance/status were never updated.
        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 100,
            'status' => 2,
            'source_provider' => 'mercadopago',
            'source_reference_id' => '111222333',
        ]);

        return [$user, $invoice->fresh()];
    }
}
