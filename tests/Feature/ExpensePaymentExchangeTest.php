<?php

namespace Tests\Feature;

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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpensePaymentExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            \Database\Seeders\PaymentTypeSeeder::class,
        ]);
    }

    public function test_store_accepts_cross_currency_payment_with_exchange_rate(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $arsAccount = $this->createArsAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'currency_id' => 978,
                'lines' => [[
                    'concept' => 'Servicio',
                    'base_amount' => '291.29',
                    'vat_percent' => '0',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'payment_date' => '2026-06-22',
                    'amount' => '500000',
                    'document_amount' => '291.29',
                    'exchange_rate' => (string) round(500000 / 291.29, 6),
                    'type_id' => $paymentType->id,
                    'account_id' => $arsAccount->id,
                    'status' => 2,
                ]],
                'submit_action' => 'save',
            ])
            ->assertRedirect(route('expense.index'));

        $payment = Payment::withoutGlobalScopes()->latest('id')->first();
        $invoice = Invoice::withoutGlobalScopes()->find($payment?->invoice_id);

        $this->assertNotNull($payment);
        $this->assertNotNull($invoice);
        $this->assertSame('500000.00', number_format((float) $payment->amount, 2, '.', ''));
        $this->assertSame('291.29', number_format((float) $payment->document_amount, 2, '.', ''));
        $this->assertEqualsWithDelta(500000 / 291.29, (float) $payment->exchange_rate, 0.000001);
        $this->assertSame('291.29', number_format((float) $invoice->total_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $invoice->balance, 2, '.', ''));
    }

    public function test_store_rejects_cross_currency_payment_without_exchange_fields(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $arsAccount = $this->createArsAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'currency_id' => 978,
                'lines' => [[
                    'concept' => 'Servicio',
                    'base_amount' => '291.29',
                    'vat_percent' => '0',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'payment_date' => '2026-06-22',
                    'amount' => '500000',
                    'type_id' => $paymentType->id,
                    'account_id' => $arsAccount->id,
                    'status' => 2,
                ]],
                'submit_action' => 'save',
            ])
            ->assertSessionHasErrors([
                'payments.0.document_amount',
                'payments.0.exchange_rate',
            ]);
    }

    public function test_draft_allows_overflow_and_currency_mismatch_without_exchange(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $arsAccount = $this->createArsAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'currency_id' => 978,
                'lines' => [[
                    'concept' => 'Servicio',
                    'base_amount' => '100.00',
                    'vat_percent' => '0',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'payment_date' => '2026-06-22',
                    'amount' => '500000',
                    'type_id' => $paymentType->id,
                    'account_id' => $arsAccount->id,
                    'status' => 2,
                ]],
                'submit_action' => 'draft',
            ])
            ->assertRedirect(route('expense.index'));

        $payment = Payment::withoutGlobalScopes()->latest('id')->first();
        $invoice = Invoice::withoutGlobalScopes()->find($payment?->invoice_id);

        $this->assertNotNull($payment);
        $this->assertNotNull($invoice);
        $this->assertSame(1, (int) $payment->status);
        $this->assertSame('500000.00', number_format((float) $payment->amount, 2, '.', ''));
        $this->assertNull($payment->document_amount);
        $this->assertSame('100.00', number_format((float) $invoice->balance, 2, '.', ''));
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createArsAccountForTeam(User $user): PaymentAccount
    {
        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'bank-ars',
            'name' => 'Banco Galicia',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);
    }

    private function createSupplierForTeam(User $user): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor de prueba',
            'email' => 'proveedor@test.test',
        ]);
    }

    private function createPaymentType(): PaymentType
    {
        return PaymentType::query()->create([
            'name' => 'Transfer',
        ]);
    }
}
