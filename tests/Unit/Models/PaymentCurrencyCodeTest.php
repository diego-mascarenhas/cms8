<?php

namespace Tests\Unit\Models;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCurrencyCodeTest extends TestCase
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

    public function test_currency_code_uses_payment_account_currency_when_available(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank-eur',
            'name' => 'Bank EUR',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $payment = new Payment([
            'account_id' => $account->id,
        ]);
        $payment->setRelation('account', $account->load('currency'));

        $this->assertSame('EUR', $payment->currency_code);
    }

    public function test_currency_code_falls_back_to_invoice_currency(): void
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
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'stripe',
            'name' => 'Stripe',
            'symbol' => '$',
            'currency_id' => null,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create([
            'name' => 'Stripe',
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => $type->id,
            'transaction_type' => 'income',
            'date' => now()->toDateString(),
            'amount' => 100,
            'status' => 2,
        ]);

        $payment->load('invoice');

        $this->assertSame('ARS', $payment->currency_code);
    }
}
