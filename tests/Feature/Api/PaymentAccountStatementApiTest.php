<?php

namespace Tests\Feature\Api;

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAccountStatementApiTest extends TestCase
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
        Storage::fake('originals');
    }

    public function test_lists_active_payment_accounts_for_team(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $eur = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'EUR',
            'name' => 'Caja Rural',
            'currency_id' => 978,
            'status' => 1,
        ]);
        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'ARS',
            'name' => 'Banco Galicia',
            'currency_id' => 32,
            'status' => 1,
        ]);
        PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'OFF',
            'name' => 'Inactiva',
            'currency_id' => 978,
            'status' => 0,
        ]);

        $token = $user->createToken('statements-api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/payment-accounts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('reporting_currency', 'EUR')
            ->assertJsonPath('default_account_id', $eur->id)
            ->assertJsonPath('data.0.name', 'Caja Rural')
            ->assertJsonPath('data.0.currency_code', 'EUR')
            ->assertJsonPath('data.1.name', 'Banco Galicia');
    }

    public function test_uploads_csv_statement_and_returns_match_summary(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'MP',
            'name' => 'MercadoPago',
            'currency_id' => 32,
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-07-10',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 1500.50,
            'status' => 2,
            'remarks' => 'Ref: ABC123',
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-07-20',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 99.00,
            'status' => 2,
        ]);

        $csv = implode("\n", [
            'DATE;amount;REFERENCE;DESCRIPTION',
            '2026-07-10;1500.50;ABC123;Transfer in',
            '2026-07-15;200.00;ONLYCSV;Not in payments',
        ]);
        $file = UploadedFile::fake()->createWithContent('extracto-2026-07.csv', $csv);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$account->id.'/statements', [
                'files' => [$file],
                'period_year' => 2026,
                'period' => 'm:7',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('uploaded', 1)
            ->assertJsonPath('statements.0.matched_lines', 1)
            ->assertJsonPath('statements.0.statement_only_lines', 1)
            ->assertJsonPath('statements.0.filename', 'extracto-2026-07.csv')
            ->assertJsonPath('statements.0.period_label', '2026-07');

        $this->assertSame(1, BankStatement::query()->where('payment_account_id', $account->id)->count());
    }

    public function test_uploads_pdf_with_statement_balance_for_quarter(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'BBVA',
            'name' => 'BBVA',
            'currency_id' => 32,
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-08-15',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 100,
            'status' => 2,
        ]);
        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => '2026-09-02',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 40,
            'status' => 2,
        ]);

        $file = UploadedFile::fake()->create('extracto-q3.pdf', 20, 'application/pdf');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$account->id.'/statements', [
                'files' => [$file],
                'period_year' => 2026,
                'period' => 'q:3',
                'statement_balance' => '60.00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('balanced', 1)
            ->assertJsonPath('open', 0)
            ->assertJsonPath('statements.0.balanced', true)
            ->assertJsonPath('statements.0.period_label', 'Q3 2026')
            ->assertJsonPath('statements.0.difference', 0);
    }

    public function test_uploads_ruralvia_xlsx_and_parses_movement_lines(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;

        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'RURALVIA',
            'name' => 'Ruralvia',
            'currency_id' => 978,
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'transaction_type' => TransactionType::INCOME,
            'date' => '2026-09-29',
            'account_id' => $account->id,
            'type_id' => 12,
            'amount' => 1847.62,
            'status' => 2,
            'remarks' => 'trf. stripe',
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Nombre', 'Revision Alpha S.L.'],
            ['IBAN', 'ES46 3059 0064 7733 1011 2929'],
            [],
            ['Fecha de la operación', 'Fecha valor', 'Tipo movimiento', 'Importe', 'Saldo', 'Nro. Apunte'],
            [46295.082824074, 46295.082824074, 'rcbo.tgss. cotizacion', -315, 5894.56, '1198'],
            [46294.082824074, 46294.082824074, 'trf. stripe', 1847.62, 6209.56, '1197'],
            [46293.082824074, 46293.082824074, 'comisión cambio divisa on line', -0.37, 4361.94, '1196'],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'ruralvia').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile(
            $path,
            'movimientos.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $this->actingAs($user, 'sanctum')
            ->post('/api/payment-accounts/'.$account->id.'/statements', [
                'files' => [$file],
                'period_year' => 2026,
                'period' => 'q:3',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('uploaded', 1)
            ->assertJsonPath('statements.0.matched_lines', 1)
            ->assertJsonPath('statements.0.statement_only_lines', 2)
            ->assertJsonPath('statements.0.filename', 'movimientos.xlsx');

        $statement = BankStatement::query()->where('payment_account_id', $account->id)->first();
        $this->assertNotNull($statement);
        $this->assertSame(3, $statement->lines()->count());
        $this->assertSame(5894.56, (float) data_get($statement->validation_summary, 'stated_balance'));
        $this->assertTrue($statement->lines()->where('external_id', '1197')->where('match_status', 'matched')->exists());

        @unlink($path);
    }

    public function test_bearer_token_can_resolve_payment_account_route_binding(): void
    {
        $user = $this->makeAdminUser();
        $teamId = (int) $user->current_team_id;
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'code' => 'BIND',
            'name' => 'Binding account',
            'currency_id' => 978,
            'status' => 1,
        ]);
        $token = $user->createToken('statement-binding')->plainTextToken;

        // withToken (not actingAs) exercises auth.api before SubstituteBindings.
        $this->withToken($token)
            ->getJson('/api/payment-accounts/'.$account->id.'/statements')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('account.id', $account->id);
    }

    public function test_lists_statements_and_reconcile_lines_for_account(): void
    {
        [$user, $bank, $statement, $line, $stripe] = $this->seedStatementWithCreditLine();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('statements.0.id', $statement->id)
            ->assertJsonPath('account.currency_code', 'EUR');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/reconcile?filter=open')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('counts.open', 1)
            ->assertJsonPath('lines.0.id', $line->id)
            ->assertJsonPath('lines.0.amount', 1847.62)
            ->assertJsonPath('transfer_accounts.0.id', $stripe->id);
    }

    public function test_api_can_transfer_and_dismiss_statement_line(): void
    {
        [$user, $bank, $statement, $line, $stripe] = $this->seedStatementWithCreditLine();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/transfer', [
                'from_account_id' => $stripe->id,
                'remarks' => 'Payout Stripe API',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('line_id', $line->id);

        $line->refresh();
        $this->assertNotNull($line->payment_id);
        $this->assertSame('matched', $line->match_status);

        $openLine = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'payment_account_id' => $bank->id,
            'external_id' => '1198',
            'reference' => 'comision',
            'description' => 'comision',
            'occurred_at' => '2026-09-28',
            'amount' => -0.37,
            'currency' => 'EUR',
            'match_status' => 'statement_only',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$openLine->id.'/dismiss')
            ->assertOk()
            ->assertJsonPath('success', true);

        $openLine->refresh();
        $this->assertTrue($openLine->isDismissed());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$openLine->id.'/undismiss')
            ->assertOk()
            ->assertJsonPath('success', true);

        $openLine->refresh();
        $this->assertFalse($openLine->isDismissed());
    }

    public function test_api_can_list_invoice_candidates_and_link_statement_line(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();
        $teamId = (int) $user->current_team_id;

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Cliente Conciliación',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-CONC-001',
            'date' => '2026-09-20',
            'due_date' => '2026-10-20',
            'gross_amount' => 2000,
            'discount' => 0,
            'total_amount' => 2000,
            'balance' => 2000,
            'status' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/invoice-candidates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('operation', 'sell')
            ->assertJsonPath('invoices.0.id', $invoice->id)
            ->assertJsonPath('invoices.0.number', 'F-CONC-001');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/link-invoice', [
                'invoice_id' => $invoice->id,
                'remarks' => 'Cobro extracto',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('line_id', $line->id);

        $line->refresh();
        $invoice->refresh();

        $this->assertNotNull($line->payment_id);
        $this->assertSame('matched', $line->match_status);
        $this->assertSame(152.38, (float) $invoice->balance);

        $payment = Payment::withoutGlobalScopes()->find($line->payment_id);
        $this->assertNotNull($payment);
        $this->assertSame(TransactionType::INCOME, $payment->transaction_type);
        $this->assertSame((int) $invoice->id, (int) $payment->invoice_id);
        $this->assertSame(1847.62, (float) $payment->amount);
        $this->assertSame(1847.62, (float) $payment->document_amount);
        $this->assertSame(1.0, (float) $payment->exchange_rate);
        $this->assertSame('manual', $payment->source_provider);
        $this->assertSame('statement-line:'.$line->id, $payment->source_reference_id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/reconcile?filter=matched')
            ->assertOk()
            ->assertJsonPath('lines.0.invoice_id', $invoice->id)
            ->assertJsonPath('lines.0.invoice_number', 'F-CONC-001')
            ->assertJsonPath('lines.0.payment_document_amount', 1847.62);
    }

    public function test_link_invoice_applies_document_amount_when_currencies_differ(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();
        $teamId = (int) $user->current_team_id;

        $line->forceFill([
            'amount' => -0.99,
            'currency' => 'EUR',
            'occurred_at' => '2026-09-17',
            'reference' => 'tj-interserver.net',
            'description' => 'tj-interserver.net',
        ])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'InterServer, Inc.',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 840,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => '49494175',
            'date' => '2026-09-17',
            'due_date' => '2026-09-17',
            'gross_amount' => 1.14,
            'discount' => 0,
            'total_amount' => 1.14,
            'balance' => 1.14,
            'status' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/link-invoice', [
                'invoice_id' => $invoice->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_amount', 1.14)
            ->assertJsonPath('exchange_rate', 0.868421)
            ->assertJsonPath('invoice_balance', 0);

        $invoice->refresh();
        $line->refresh();
        $payment = Payment::withoutGlobalScopes()->find($line->payment_id);

        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertNotNull($payment);
        $this->assertSame(0.99, (float) $payment->amount);
        $this->assertSame(1.14, (float) $payment->document_amount);
        $this->assertSame(0.868421, (float) $payment->exchange_rate);
        $this->assertSame(TransactionType::EXPENSE, $payment->transaction_type);

        $paymentId = (int) $payment->id;

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/reconcile?filter=matched')
            ->assertOk()
            ->assertJsonPath('lines.0.can_unlink', true)
            ->assertJsonPath('lines.0.invoice_id', $invoice->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/unlink')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice_balance', 1.14)
            ->assertJsonPath('deleted_payment_ids.0', $paymentId);

        $line->refresh();
        $invoice->refresh();

        $this->assertNull($line->payment_id);
        $this->assertSame('statement_only', $line->match_status);
        $this->assertSame(1.14, (float) $invoice->balance);
        $this->assertNull(Payment::withoutGlobalScopes()->find($paymentId));
    }

    public function test_link_invoice_can_split_one_movement_across_multiple_invoices(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();
        $teamId = (int) $user->current_team_id;

        $line->forceFill([
            'amount' => -859.20,
            'currency' => 'EUR',
            'occurred_at' => '2026-01-02',
            'reference' => 'tj-ovhcloud',
            'description' => 'tj-ovhcloud',
        ])->save();

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'OVH HISPANO S.L.',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $mainInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'ES4114126',
            'date' => '2026-01-01',
            'due_date' => '2026-01-01',
            'gross_amount' => 845.87,
            'discount' => 0,
            'total_amount' => 845.87,
            'balance' => 845.87,
            'status' => 2,
        ]);

        $smallInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'ES4128674',
            'date' => '2026-01-01',
            'due_date' => '2026-01-01',
            'gross_amount' => 13.33,
            'discount' => 0,
            'total_amount' => 13.33,
            'balance' => 13.33,
            'status' => 2,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/link-invoice', [
                'invoice_ids' => [$mainInvoice->id, $smallInvoice->id],
                'remarks' => 'OVH cloud',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice_id', $mainInvoice->id)
            ->assertJsonPath('invoice_ids.0', $mainInvoice->id)
            ->assertJsonPath('invoice_ids.1', $smallInvoice->id)
            ->assertJsonPath('redirect_url', '/expense/edit/'.$mainInvoice->id)
            ->assertJsonPath('invoice_operation', 'buy');

        $line->refresh();
        $mainInvoice->refresh();
        $smallInvoice->refresh();

        $this->assertNotNull($line->payment_id);
        $this->assertSame('matched', $line->match_status);
        $this->assertSame(0.0, (float) $mainInvoice->balance);
        $this->assertSame(0.0, (float) $smallInvoice->balance);

        $payments = Payment::withoutGlobalScopes()
            ->where(function ($query) use ($line): void
            {
                $query->where('source_reference_id', 'statement-line:'.$line->id)
                    ->orWhere('source_reference_id', 'like', 'statement-line:'.$line->id.':%');
            })
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $payments);
        $this->assertSame(845.87, (float) $payments[0]->amount);
        $this->assertSame((int) $mainInvoice->id, (int) $payments[0]->invoice_id);
        $this->assertSame(13.33, (float) $payments[1]->amount);
        $this->assertSame((int) $smallInvoice->id, (int) $payments[1]->invoice_id);
        $this->assertSame((int) $payments[0]->id, (int) $line->payment_id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/reconcile?filter=matched')
            ->assertOk()
            ->assertJsonPath('lines.0.linked_invoices.0.invoice_id', $mainInvoice->id)
            ->assertJsonPath('lines.0.linked_invoices.0.amount', 845.87)
            ->assertJsonPath('lines.0.linked_invoices.1.invoice_id', $smallInvoice->id)
            ->assertJsonPath('lines.0.linked_invoices.1.amount', 13.33)
            ->assertJsonPath('lines.0.can_unlink', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/unlink')
            ->assertOk()
            ->assertJsonPath('success', true);

        $line->refresh();
        $mainInvoice->refresh();
        $smallInvoice->refresh();

        $this->assertNull($line->payment_id);
        $this->assertSame('statement_only', $line->match_status);
        $this->assertSame(845.87, (float) $mainInvoice->balance);
        $this->assertSame(13.33, (float) $smallInvoice->balance);
        $this->assertCount(
            0,
            Payment::withoutGlobalScopes()
                ->where(function ($query) use ($line): void
                {
                    $query->where('source_reference_id', 'statement-line:'.$line->id)
                        ->orWhere('source_reference_id', 'like', 'statement-line:'.$line->id.':%');
                })
                ->get(),
        );
    }

    public function test_invoice_candidates_rank_by_bank_alias_amount_and_date(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();
        $teamId = (int) $user->current_team_id;

        $line->forceFill([
            'reference' => 'tj-web*network solutions',
            'description' => 'tj-web*network solutions',
            'amount' => 24.19,
            'occurred_at' => '2026-09-25',
        ])->save();

        $matched = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Network Solutions, LLC',
            'type_id' => 1,
            'status_id' => 1,
            'data' => [
                'bank_aliases' => ['tj-web*network solutions'],
                'bank_remarks' => 'Cobro Network Solutions',
            ],
        ]);
        $other = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'name' => 'Otra Empresa',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $preferred = Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $matched->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-NET-001',
            'date' => '2026-09-20',
            'due_date' => '2026-10-20',
            'gross_amount' => 24.19,
            'discount' => 0,
            'total_amount' => 24.19,
            'balance' => 24.19,
            'status' => 2,
        ]);
        Invoice::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $other->id,
            'currency_id' => 978,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-OTHER-001',
            'date' => '2026-09-24',
            'due_date' => '2026-10-24',
            'gross_amount' => 24.19,
            'discount' => 0,
            'total_amount' => 24.19,
            'balance' => 24.19,
            'status' => 2,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id.'/lines/'.$line->id.'/invoice-candidates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('suggested_invoice_id', $preferred->id)
            ->assertJsonPath('suggested_remarks', 'Cobro Network Solutions')
            ->assertJsonPath('invoices.0.id', $preferred->id)
            ->assertJsonPath('invoices.0.matched_by_enterprise', true);
    }

    public function test_api_can_delete_uploaded_statement(): void
    {
        [$user, $bank, $statement, $line] = $this->seedStatementWithCreditLine();

        $statement->forceFill([
            'disk' => 'originals',
            'storage_path' => 'statements/test-delete.csv',
            'original_filename' => 'test-delete.csv',
            'file_size' => 120,
        ])->save();

        Storage::disk('originals')->put('statements/test-delete.csv', "DATE;amount\n2026-09-29;10");

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/payment-accounts/'.$bank->id.'/statements/'.$statement->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('bank_statements', ['id' => $statement->id]);
        $this->assertDatabaseMissing('bank_statement_lines', ['id' => $line->id]);
        $this->assertFalse(Storage::disk('originals')->exists('statements/test-delete.csv'));
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
                'line_count' => 1,
                'matched' => 0,
                'statement_only' => 1,
                'payment_only' => 0,
                'balanced' => false,
            ],
        ]);

        $line = BankStatementLine::query()->create([
            'bank_statement_id' => $statement->id,
            'payment_account_id' => $bank->id,
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
