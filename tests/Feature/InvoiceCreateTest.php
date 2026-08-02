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

class InvoiceCreateTest extends TestCase
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

    public function test_create_page_uses_manual_sales_invoice_form(): void
    {
        $user = $this->makeAdminUser();
        $client = $this->createClientForTeam($user);
        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Hidden supplier',
        ]);
        $this->createAccountForTeam($user);
        $this->createPaymentType();

        $response = $this->actingAs($user)->get(route('invoice.create'));

        $response
            ->assertOk()
            ->assertSee('Crear factura', false)
            ->assertSee('Cliente (*)', false)
            ->assertSee($client->name, false)
            ->assertDontSee($supplier->name, false)
            ->assertSee('action="'.route('invoice.store').'"', false)
            ->assertDontSee('Invoice To:', false)
            ->assertDontSee('Suelta un archivo o haz clic para subir', false)
            ->assertDontSee('Opcional: factura, ticket o documento fiscal', false)
            ->assertSee('Cobros', false)
            ->assertSee('Añadir cobro', false)
            ->assertDontSee('>Pagos</h5>', false)
            ->assertDontSee('>Añadir pago<', false);
    }

    public function test_store_creates_sell_invoice_and_income_payment(): void
    {
        Storage::fake('public');

        $user = $this->makeAdminUser();
        $client = $this->createClientForTeam($user);
        $account = $this->createAccountForTeam($user);
        $paymentType = $this->createPaymentType();

        $this->actingAs($user)
            ->post(route('invoice.store'), [
                'document_type' => 'invoice',
                'enterprise_id' => $client->id,
                'date' => '2026-08-03',
                'lines' => [[
                    'concept' => 'Consulting services',
                    'base_amount' => '100.00',
                    'vat_percent' => '21',
                    'retention_percent' => '0',
                    'allocation_percent' => '100',
                ]],
                'payments' => [[
                    'payment_date' => '2026-08-03',
                    'amount' => '121.00',
                    'type_id' => $paymentType->id,
                    'account_id' => $account->id,
                    'status' => 2,
                ]],
                'submit_action' => 'save',
                'document_file' => UploadedFile::fake()->create('Sales Invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('invoice.index'))
            ->assertSessionHas('success');

        $payment = Payment::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame('income', $payment->transaction_type->value);

        $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame('sell', $invoice->operation);
        $this->assertStringStartsWith('FV-', $invoice->number);
        $this->assertSame($client->id, $invoice->enterprise_id);
        $this->assertSame('121.00', number_format((float) $invoice->total_amount, 2, '.', ''));

        $teamHash = Team::generateTeamHash((int) $user->current_team_id);
        $this->assertNotEmpty(Storage::disk('public')->allFiles("invoices/{$teamHash}"));
        $this->assertStringContainsString('/storage/invoices/'.$teamHash.'/', (string) $payment->remarks);
    }

    public function test_create_client_endpoint_returns_client_enterprise(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->postJson(route('invoice.create-client'), [
                'name' => 'New Client SL',
                'email' => 'billing@client.test',
                'country' => 'ES',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('enterprise.name', 'New Client SL')
            ->assertJsonPath('enterprise.type_id', 1);

        $this->assertDatabaseHas('enterprises', [
            'team_id' => (int) $user->current_team_id,
            'name' => 'New Client SL',
            'type_id' => 1,
            'status_id' => 2,
        ]);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function createClientForTeam(User $user): Enterprise
    {
        return Enterprise::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Test client',
            'email' => 'client@test.test',
        ]);
    }

    private function createAccountForTeam(User $user): PaymentAccount
    {
        return PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => (int) $user->current_team_id,
            'code' => 'sales-bank',
            'name' => 'Sales bank account',
            'symbol' => '€',
            'currency_id' => 978,
            'status' => 1,
        ]);
    }

    private function createPaymentType(): PaymentType
    {
        return PaymentType::query()->create([
            'name' => 'Bank transfer',
        ]);
    }
}
