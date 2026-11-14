<?php

namespace Tests\Feature\Api;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Finance\ManualInvoiceDocumentService;
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

class ExpenseEditApiTest extends TestCase
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
            PaymentTypeSeeder::class,
        ]);
    }

    public function test_show_returns_the_expense_with_lines_and_payments(): void
    {
        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense($token, $supplier, $account, $paymentType, 'EDIT-1');

        $this->withToken($token)
            ->getJson('/api/expenses/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('expense.id', $invoice->id)
            ->assertJsonPath('expense.enterprise_id', $supplier->id)
            ->assertJsonPath('expense.document_number', 'EDIT-1')
            ->assertJsonPath('expense.document_type', 'invoice')
            ->assertJsonPath('expense.lines.0.concept', 'Hosting')
            ->assertJsonPath('expense.lines.0.base_amount', 10)
            ->assertJsonPath('expense.lines.0.vat_percent', 21)
            ->assertJsonPath('expense.payments.0.amount', 12.1)
            ->assertJsonPath('expense.has_document', false)
            ->assertJsonFragment(['name' => 'Proveedor de prueba']);
    }

    public function test_show_rejects_invoices_from_other_teams_and_sales(): void
    {
        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense($token, $supplier, $account, $paymentType, 'EDIT-2');

        $invoice->forceFill(['operation' => 'sell'])->save();
        $this->withToken($token)->getJson('/api/expenses/'.$invoice->id)->assertNotFound();

        $invoice->forceFill(['operation' => 'buy'])->save();
        $otherUser = $this->makeAdminUser();
        $otherToken = $otherUser->createToken('other')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/expenses/'.$invoice->id)->assertNotFound();
    }

    public function test_update_changes_concept_and_payment_amount(): void
    {
        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense($token, $supplier, $account, $paymentType, 'EDIT-3');
        $paymentId = (int) Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->value('id');

        $this->withToken($token)
            ->postJson('/api/expenses/'.$invoice->id, $this->payload($supplier, $account, $paymentType, 'EDIT-3', [
                'lines' => [[
                    'concept' => 'Hosting premium',
                    'base_amount' => '20.00',
                    'vat_percent' => '21',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'id' => $paymentId,
                    'payment_date' => '2026-11-13',
                    'amount' => '10.00',
                    'type_id' => $paymentType->id,
                    'account_id' => $account->id,
                    'status' => 2,
                ], [
                    'payment_date' => '2026-11-14',
                    'amount' => '5.00',
                    'type_id' => $paymentType->id,
                    'account_id' => $account->id,
                    'status' => 2,
                ]],
            ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invoice_id', $invoice->id);

        $invoice->refresh();
        $this->assertSame('24.20', number_format((float) $invoice->total_amount, 2, '.', ''));
        $this->assertSame('9.20', number_format((float) $invoice->balance, 2, '.', ''));
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('Hosting premium', $invoice->items()->first()->description);

        $payments = Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame($paymentId, (int) $payments[0]->id);
        $this->assertSame('10.00', number_format((float) $payments[0]->amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) $payments[1]->amount, 2, '.', ''));

        $this->withToken($token)
            ->postJson('/api/expenses/'.$invoice->id, $this->payload($supplier, $account, $paymentType, 'EDIT-3', [
                'payments' => [[
                    'id' => $paymentId,
                    'payment_date' => '2026-11-13',
                    'amount' => '10.00',
                    'type_id' => $paymentType->id,
                    'account_id' => $account->id,
                    'status' => 2,
                ]],
            ]))
            ->assertOk();

        $this->assertSame(
            1,
            Payment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count(),
        );
    }

    public function test_update_does_not_flag_its_own_number_as_duplicate_but_rejects_other_invoices(): void
    {
        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $first = $this->createExpense($token, $supplier, $account, $paymentType, 'DUP-1');
        $this->createExpense($token, $supplier, $account, $paymentType, 'DUP-2');

        $this->withToken($token)
            ->postJson('/api/expenses/'.$first->id, $this->payload($supplier, $account, $paymentType, 'DUP-1'))
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/expenses/'.$first->id, $this->payload($supplier, $account, $paymentType, 'DUP-2'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_number');
    }

    public function test_document_download_streams_the_stored_file(): void
    {
        Storage::fake('public');

        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense(
            $token,
            $supplier,
            $account,
            $paymentType,
            'DOC-1',
            ['document_file' => UploadedFile::fake()->createWithContent('factura.pdf', '%PDF-1.4 test')],
        );

        $storedPath = app(ManualInvoiceDocumentService::class)->storedRelativePath($invoice);
        $this->assertNotNull($storedPath);

        $this->withToken($token)
            ->getJson('/api/expenses/'.$invoice->id)
            ->assertJsonPath('expense.has_document', true)
            ->assertJsonPath('expense.document_name', basename((string) $storedPath));

        $response = $this->withToken($token)->get('/api/expenses/'.$invoice->id.'/document');
        $response->assertOk();
        $this->assertStringContainsString('%PDF-1.4 test', $response->streamedContent());

        $download = $this->withToken($token)->get('/api/expenses/'.$invoice->id.'/document?download=1');
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));

        $this->actingAs($user)
            ->get(route('invoice.document.download', $invoice))
            ->assertOk();
    }

    public function test_update_can_replace_the_document(): void
    {
        Storage::fake('public');

        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense(
            $token,
            $supplier,
            $account,
            $paymentType,
            'DOC-2',
            ['document_file' => UploadedFile::fake()->createWithContent('old.pdf', 'old')],
        );

        $this->withToken($token)
            ->post('/api/expenses/'.$invoice->id, $this->payload($supplier, $account, $paymentType, 'DOC-2', [
                'document_file' => UploadedFile::fake()->createWithContent('new.pdf', 'new'),
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $storedPath = app(ManualInvoiceDocumentService::class)->storedRelativePath($invoice);
        $this->assertStringStartsWith('new', basename((string) $storedPath));
        $this->assertCount(1, Storage::disk('public')->files(dirname((string) $storedPath)));
    }

    public function test_document_download_returns_not_found_without_a_file(): void
    {
        Storage::fake('public');

        [$user, $supplier, $account, $paymentType] = $this->makeContext();
        $token = $user->createToken('expense-api')->plainTextToken;
        $invoice = $this->createExpense($token, $supplier, $account, $paymentType, 'DOC-3');

        $this->withToken($token)
            ->get('/api/expenses/'.$invoice->id.'/document', ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(
        Enterprise $supplier,
        PaymentAccount $account,
        PaymentType $paymentType,
        string $number,
        array $overrides = [],
    ): array {
        return array_merge([
            'document_type' => 'invoice',
            'enterprise_id' => $supplier->id,
            'date' => '2026-11-12',
            'document_number' => $number,
            'lines' => [[
                'concept' => 'Hosting',
                'base_amount' => '10.00',
                'vat_percent' => '21',
                'retention_percent' => '0',
                'allocation_percent' => '100',
            ]],
            'payments' => [[
                'payment_date' => '2026-11-12',
                'amount' => '12.10',
                'type_id' => $paymentType->id,
                'account_id' => $account->id,
                'status' => 2,
            ]],
            'submit_action' => 'save',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createExpense(
        string $token,
        Enterprise $supplier,
        PaymentAccount $account,
        PaymentType $paymentType,
        string $number,
        array $overrides = [],
    ): Invoice {
        $payload = $this->payload($supplier, $account, $paymentType, $number, $overrides);

        $request = $this->withToken($token);
        $response = isset($overrides['document_file'])
            ? $request->post('/api/expenses', $payload, ['Accept' => 'application/json'])
            : $request->postJson('/api/expenses', $payload);

        $response->assertOk();

        return Invoice::withoutGlobalScopes()->findOrFail((int) $response->json('invoice_id'));
    }

    /**
     * @return array{0: User, 1: Enterprise, 2: PaymentAccount, 3: PaymentType}
     */
    private function makeContext(): array
    {
        $user = $this->makeAdminUser();
        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor de prueba',
            'email' => 'proveedor@test.test',
        ]);
        $account = PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'bank-main',
            'name' => 'Main bank account',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);
        $paymentType = PaymentType::query()->create(['name' => 'Transfer']);

        return [$user, $supplier, $account, $paymentType];
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }
}
