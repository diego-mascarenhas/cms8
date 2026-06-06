<?php

namespace Tests\Unit\Services\Finance;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Finance\PaymentInvoiceLinkService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInvoiceLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentInvoiceLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        $this->service = app(PaymentInvoiceLinkService::class);
    }

    public function test_invoices_for_payment_only_includes_outstanding_balance_for_matching_operation(): void
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

        $paidInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-PAID',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
        ]);

        $pendingInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-PENDING',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 200,
            'discount' => 0,
            'total_amount' => 200,
            'balance' => 200,
            'status' => 2,
        ]);

        $buyInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'F-BUY',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 300,
            'discount' => 0,
            'total_amount' => 300,
            'balance' => 300,
            'status' => 2,
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank',
            'name' => 'Bank',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create(['name' => 'Transferencia']);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
            'amount' => 200,
            'status' => 2,
        ]);

        $invoiceIds = $this->service->invoicesForPayment($payment)->pluck('id')->all();

        $this->assertContains($pendingInvoice->id, $invoiceIds);
        $this->assertNotContains($paidInvoice->id, $invoiceIds);
        $this->assertNotContains($buyInvoice->id, $invoiceIds);
    }

    public function test_link_payment_to_invoice_assigns_enterprise_when_missing(): void
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

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 2,
        ]);

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'bank',
            'name' => 'Bank',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $type = PaymentType::query()->create(['name' => 'Transferencia']);

        $payment = Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => null,
            'transaction_type' => TransactionType::INCOME,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'type_id' => $type->id,
            'amount' => 100,
            'status' => 2,
        ]);

        $this->service->linkPaymentToInvoice($payment, $invoice);

        $payment->refresh();

        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame($enterprise->id, $payment->enterprise_id);
    }
}
