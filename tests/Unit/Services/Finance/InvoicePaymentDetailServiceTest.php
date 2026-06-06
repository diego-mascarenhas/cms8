<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Finance\InvoicePaymentDetailService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePaymentDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $this->service = app(InvoicePaymentDetailService::class);
    }

    public function test_it_returns_linked_payment_method_and_account(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank',
            'name' => 'Cuenta EUR',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create([
            'name' => 'Transferencia',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 192,
            'discount' => 76.8,
            'total_amount' => 115.2,
            'balance' => 0,
            'status' => 2,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'transaction_type' => 'income',
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
            'amount' => 115.2,
            'status' => 2,
        ]);

        $details = $this->service->forInvoice($invoice);

        $this->assertCount(1, $details);
        $this->assertSame('Transferencia', $details->first()['method']);
        $this->assertSame('Cuenta EUR', $details->first()['account']);
        $this->assertSame(115.2, $details->first()['amount']);
        $this->assertSame('EUR', $details->first()['currency_code']);
    }

    public function test_it_falls_back_to_stripe_payment_sync_when_core_payment_is_missing(): void
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

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'ch_test_charge',
            'status' => 'succeeded',
            'currency' => 'EUR',
            'amount_net_cents' => 11520,
            'invoice_external_id' => 'in_test_invoice',
            'description' => 'Hosting example',
            'charge_created_at' => now(),
            'last_synced_at' => now(),
        ]);

        $details = $this->service->forInvoice($invoice);

        $this->assertCount(1, $details);
        $this->assertSame('Stripe', $details->first()['method']);
        $this->assertSame('Stripe', $details->first()['account']);
        $this->assertSame(115.2, $details->first()['amount']);
    }
}
