<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceShowDocumentLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_invoice_show_links_print_and_download_to_stripe_urls(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

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
            'number' => '0005-0200',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_test_documents',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_test_documents',
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/test_hosted',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/test/pdf',
            'raw_payload' => [
                'livemode' => false,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee('https://invoice.stripe.com/i/test_hosted', false)
            ->assertSee('https://pay.stripe.com/invoice/test/pdf', false)
            ->assertSee('https://dashboard.stripe.com/test/invoices/in_test_documents', false)
            ->assertSee(__('View in Stripe'), false);
    }

    public function test_invoice_show_displays_team_reporting_currency_conversion(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('finance_reporting_currency', 'EUR', ['group' => 'finance']);

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
            'number' => '0005-0965',
            'date' => '2026-07-22',
            'due_date' => '2026-07-30',
            'gross_amount' => 10000,
            'discount' => 0,
            'total_amount' => 10000,
            'balance' => 0,
            'status' => 2,
        ]);

        \App\Models\ExchangeRate::query()->create([
            'base_currency' => 'ARS',
            'target_currency' => 'EUR',
            'rate' => 0.001,
            'date' => '2026-07-01',
            'fetched_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee('10.000,00 ARS', false)
            ->assertSee('10,00 EUR', false)
            ->assertSee(__('Exchange rate: 1 :reporting = :rate :native', [
                'reporting' => 'EUR',
                'native' => 'ARS',
                'rate' => '1.000,0000',
            ]), false)
            ->assertDontSee('Conversión a EUR', false);
    }

    public function test_invoice_show_displays_client_tax_due_date_and_payment_method(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->seed([
            \Database\Seeders\EnterpriseTaxStatusTypeSeeder::class,
            \Database\Seeders\PaymentTypeSeeder::class,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Clean Up',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $taxStatus = \App\Models\EnterpriseTaxStatusType::query()->firstOrFail();

        \App\Models\EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'CLEAN UP BUENOS AIRES SRL',
            'tax_status_type_id' => $taxStatus->id,
            'identification_number' => '30717198561',
            'address' => 'Av. Test 123',
            'postal_code' => '1000',
            'locality' => 'CABA',
            'country' => 'AR',
            'status' => 1,
        ]);

        $account = \App\Models\PaymentAccount::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'code' => 'mp-ars',
            'name' => 'MercadoPago',
            'symbol' => '$',
            'currency_id' => 32,
            'status' => 1,
        ]);

        $paymentType = \App\Models\PaymentType::query()->firstOrCreate(['name' => 'MercadoPago']);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'currency_id' => 32,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0965',
            'date' => '2026-07-22',
            'due_date' => null,
            'gross_amount' => 10000,
            'discount' => 0,
            'total_amount' => 10000,
            'balance' => 0,
            'status' => 2,
        ]);

        \App\Models\Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'invoice_id' => $invoice->id,
            'account_id' => $account->id,
            'type_id' => $paymentType->id,
            'transaction_type' => 'income',
            'amount' => 10000,
            'date' => '2026-07-22',
            'status' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('invoice.show', $invoice->id))
            ->assertOk()
            ->assertSee('22-07-2026', false)
            ->assertSee(__('Due Date'), false)
            ->assertSee('30717198561', false)
            ->assertSee($taxStatus->name, false)
            ->assertSee('MercadoPago', false)
            ->assertSee(__('invoice_payment.type'), false);
    }
}
