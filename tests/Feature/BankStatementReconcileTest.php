<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankStatementReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            PaymentTypeSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);
    }

    public function test_admin_can_view_statement_reconcile_lines(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();

        $this->actingAs($user)
            ->get(route('payment-account.statements.reconcile', [$bank, $statement]))
            ->assertOk()
            ->assertSee('trf. stripe')
            ->assertSee('1.847,62')
            ->assertSee(__('Traspaso'));
    }

    public function test_admin_can_register_transfer_from_stripe_to_bank_line(): void
    {
        [$user, $bank, $statement, $line, $stripe] = $this->seedStatementWithCreditLine();

        $this->actingAs($user)
            ->post(route('payment-account.statements.lines.transfer', [$bank, $statement, $line]), [
                'from_account_id' => $stripe->id,
                'remarks' => 'Payout Stripe septiembre',
            ])
            ->assertRedirect(route('payment-account.statements.reconcile', [$bank, $statement, 'filter' => 'matched']))
            ->assertSessionHas('success');

        $line->refresh();
        $this->assertNotNull($line->payment_id);
        $this->assertSame('matched', $line->match_status);

        $inflow = Payment::withoutGlobalScopes()->find($line->payment_id);
        $this->assertNotNull($inflow);
        $this->assertSame(TransactionType::INCOME, $inflow->transaction_type);
        $this->assertSame((int) $bank->id, (int) $inflow->account_id);
        $this->assertSame('transfer', $inflow->source_provider);
        $this->assertSame(1847.62, (float) $inflow->amount);

        $this->assertNotNull($inflow->source_reference_id);
        $this->assertStringStartsWith('transfer:', (string) $inflow->source_reference_id);
        $this->assertStringEndsWith(':in', (string) $inflow->source_reference_id);

        $pairKey = preg_replace('/:(in|out)$/', '', (string) $inflow->source_reference_id);
        $outflow = Payment::withoutGlobalScopes()
            ->where('source_reference_id', $pairKey.':out')
            ->where('transaction_type', TransactionType::EXPENSE)
            ->first();

        $this->assertNotNull($outflow);
        $this->assertSame((int) $stripe->id, (int) $outflow->account_id);
        $this->assertSame(1847.62, (float) $outflow->amount);
    }

    public function test_admin_can_dismiss_and_restore_statement_line(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();

        $this->actingAs($user)
            ->post(route('payment-account.statements.lines.dismiss', [$bank, $statement, $line]))
            ->assertRedirect();

        $line->refresh();
        $this->assertTrue($line->isDismissed());
        $this->assertSame('dismissed', $line->match_status);

        $this->actingAs($user)
            ->post(route('payment-account.statements.lines.undismiss', [$bank, $statement, $line]))
            ->assertRedirect();

        $line->refresh();
        $this->assertFalse($line->isDismissed());
        $this->assertSame('statement_only', $line->match_status);
    }

    public function test_admin_can_link_open_line_to_existing_invoice(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();
        $teamId = (int) $user->current_team_id;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Cliente Web',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-WEB-001',
            'date' => '2026-09-20',
            'due_date' => '2026-10-20',
            'gross_amount' => 2000,
            'discount' => 0,
            'total_amount' => 2000,
            'balance' => 2000,
            'status' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('payment-account.statements.reconcile', [$bank, $statement]))
            ->assertOk()
            ->assertSee(__('Asociar factura'))
            ->assertSee(__('Archivos subidos'));

        $this->actingAs($user)
            ->post(route('payment-account.statements.lines.link-invoice', [$bank, $statement, $line]), [
                'invoice_id' => $invoice->id,
                'remarks' => 'Cobro desde extracto',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $line->refresh();
        $invoice->refresh();

        $this->assertNotNull($line->payment_id);
        $this->assertSame('matched', $line->match_status);
        $this->assertSame(152.38, (float) $invoice->balance);

        $paymentId = (int) $line->payment_id;

        $this->actingAs($user)
            ->post(route('payment-account.statements.lines.unlink', [$bank, $statement, $line]))
            ->assertRedirect(route('payment-account.statements.reconcile', [$bank, $statement, 'filter' => 'open']))
            ->assertSessionHas('success');

        $line->refresh();
        $invoice->refresh();

        $this->assertNull($line->payment_id);
        $this->assertSame('statement_only', $line->match_status);
        $this->assertSame(2000.0, (float) $invoice->balance);
        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
    }

    public function test_admin_can_delete_statement_from_account_show(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();

        $this->actingAs($user)
            ->delete(route('payment-account.statements.destroy', [$bank, $statement]))
            ->assertRedirect(route('payment-account.show', $bank))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bank_statements', ['id' => $statement->id]);
        $this->assertDatabaseMissing('bank_statement_lines', ['id' => $line->id]);
    }

    /**
     * @return array{0: User, 1: PaymentAccount, 2: BankStatement, 3: BankStatementLine, 4: PaymentAccount}
     */
    private function seedStatementWithCreditLine(): array
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $bank = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'RURALVIA',
            'name' => 'Caja Rural',
            'currency_id' => 978,
            'status' => 1,
        ]);
        $stripe = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'STRIPE',
            'name' => 'Stripe',
            'currency_id' => 978,
            'status' => 1,
        ]);

        $statement = BankStatement::query()->create([
            'team_id' => $teamId,
            'payment_account_id' => $bank->id,
            'provider' => BankStatement::PROVIDER_UPLOAD,
            'period_year' => 2026,
            'period_month' => 9,
            'source' => BankStatement::SOURCE_UPLOAD,
            'original_filename' => 'movimientos.xlsx',
            'validation_summary' => [
                'period_kind' => 'quarter',
                'period_quarter' => 3,
                'stated_balance' => 5894.56,
            ],
        ]);

        $line = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'external_id' => '1197',
            'reference' => 'trf. stripe',
            'description' => 'trf. stripe',
            'occurred_at' => '2026-09-29',
            'amount' => 1847.62,
            'currency' => 'EUR',
            'match_status' => 'statement_only',
        ]);

        return [$user, $bank, $statement, $line, $stripe];
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
