<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Finance\InvoicePaymentRegistrationService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePaymentRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $this->service = app(InvoicePaymentRegistrationService::class);
    }

    public function test_form_defaults_use_balance_today_and_matching_currency_account(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $matchingAccount = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-ars',
            'name' => 'Banco ARS',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Banco EUR',
            'symbol' => '€',
            'currency_id' => 978,
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
            'gross_amount' => 41818.18,
            'discount' => 0,
            'total_amount' => 41818.18,
            'balance' => 41818.18,
            'status' => 2,
        ]);

        $defaults = $this->service->formDefaults($invoice);

        $this->assertSame(41818.18, $defaults['amount']);
        $this->assertSame(now()->toDateString(), $defaults['date']);
        $this->assertSame($matchingAccount->id, $defaults['account_id']);
        $this->assertSame('ARS', $defaults['currency_code']);
        $this->assertCount(1, $defaults['accounts']);
    }

    public function test_register_creates_payment_and_reduces_invoice_balance(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
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

        $payment = $this->service->register($user, $invoice, [
            'amount' => 40,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
        ]);

        $invoice->refresh();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(TransactionType::INCOME, $payment->transaction_type);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(60.0, (float) $invoice->balance);
    }

    public function test_non_owner_cannot_register_payment(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->first();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($member);

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
            'number' => 'F-002',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 50,
            'discount' => 0,
            'total_amount' => 50,
            'balance' => 50,
            'status' => 2,
        ]);

        $this->assertFalse($this->service->canRegisterPayment($member, $invoice));
    }

    public function test_cannot_register_payment_when_balance_is_zero(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Banco EUR',
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
            'number' => 'F-003',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $this->assertFalse($this->service->canRegisterPayment($user, $invoice));
    }

    public function test_cannot_register_payment_for_blocked_statuses(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Banco EUR',
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
            'balance' => 100,
            'status' => 5,
        ]);

        $this->assertFalse($this->service->canRegisterPayment($user, $invoice));
    }

    public function test_cannot_register_payment_when_stripe_invoice_is_already_paid(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Banco EUR',
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
            'number' => 'F-005',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_paid_sync',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_paid_sync',
            'status' => 'paid',
            'paid' => true,
        ]);

        $invoice->setRelation('stripeInvoiceSync', InvoiceSync::query()->first());

        $this->assertFalse($this->service->canRegisterPayment($user, $invoice));
    }
}
