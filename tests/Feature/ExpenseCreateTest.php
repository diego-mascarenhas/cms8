<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentType;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpenseCreateTest extends TestCase
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
        ]);
    }

    public function test_create_page_is_accessible_for_authenticated_user(): void
    {
        $user = $this->makeAdminUser();
        $account = $this->createAccountForTeam($user);
        $this->createPaymentType();

        $response = $this->actingAs($user)->get(route('expense.create'));

        $response
            ->assertOk()
            ->assertSee('Registrar un nuevo gasto', false)
            ->assertSee($account->name, false);
    }

    public function test_store_creates_expense_payment_and_redirects(): void
    {
        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $account = $this->createAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'document_number' => 'FAC-001',
                'expense_category' => 'Software subscriptions',
                'lines' => [
                    [
                        'concept' => 'Monthly SaaS',
                        'base_amount' => '100.00',
                        'vat_percent' => '21',
                        'retention_percent' => '0',
                        'allocation_percent' => '100',
                    ],
                ],
                'payment_date' => '2026-06-22',
                'payment_amount' => '',
                'type_id' => $paymentType->id,
                'account_id' => $account->id,
                'status' => 2,
                'remarks' => 'Main operations tool',
                'tags' => 'operations,saas',
                'submit_action' => 'save',
            ])
            ->assertRedirect(route('expense.index'))
            ->assertSessionHas('success');

        $payment = Payment::withoutGlobalScopes()->latest()->first();

        $this->assertNotNull($payment);
        $this->assertSame('expense', $payment->transaction_type->value);
        $this->assertSame($supplier->id, $payment->enterprise_id);
        $this->assertSame($account->id, $payment->account_id);
        $this->assertSame($paymentType->id, $payment->type_id);
        $this->assertSame(2, $payment->status);
        $this->assertSame('121.00', number_format((float) $payment->amount, 2, '.', ''));

        $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame('buy', $invoice->operation);
        $this->assertSame('FAC-001', $invoice->number);
        $this->assertSame($supplier->id, $invoice->enterprise_id);
        $this->assertSame('121.00', number_format((float) $invoice->total_amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $invoice->balance, 2, '.', ''));
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->post(route('expense.store'), [])
            ->assertSessionHasErrors([
                'enterprise_id',
                'date',
                'lines',
                'payment_date',
                'type_id',
                'account_id',
                'status',
            ]);
    }

    public function test_store_persists_uploaded_document_using_team_hash_path(): void
    {
        Storage::fake('public');

        $user = $this->makeAdminUser();
        $supplier = $this->createSupplierForTeam($user);
        $account = $this->createAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('expense.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $supplier->id,
                'date' => '2026-06-22',
                'document_number' => 'FAC-HASH-001',
                'expense_category' => 'Comunicaciones',
                'lines' => [
                    [
                        'concept' => 'Factura mensual telefonía',
                        'base_amount' => '100.00',
                        'vat_percent' => '21',
                        'retention_percent' => '0',
                        'allocation_percent' => '100',
                    ],
                ],
                'payment_date' => '2026-06-22',
                'payment_amount' => '',
                'type_id' => $paymentType->id,
                'account_id' => $account->id,
                'status' => 2,
                'submit_action' => 'save',
                'document_file' => UploadedFile::fake()->create('Factura Yoigo Enero.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('expense.index'));

        $teamHash = Team::generateTeamHash((int) $user->current_team_id);
        $storedFiles = Storage::disk('public')->allFiles("expenses/{$teamHash}");

        $this->assertNotEmpty($storedFiles);

        $payment = Payment::withoutGlobalScopes()->latest()->first();
        $this->assertNotNull($payment);
        $this->assertStringContainsString('/storage/expenses/'.$teamHash.'/', (string) $payment->remarks);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createAccountForTeam(User $user): PaymentAccount
    {
        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'bank-main',
            'name' => 'Main bank account',
            'symbol' => '€',
            'currency_id' => 978,
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
