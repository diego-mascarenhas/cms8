<?php

namespace Tests\Feature\Fiscal;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceFiscalExportButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'invoice.edit', 'guard_name' => 'web']);

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        config([
            'fiscal.enabled' => true,
            'fiscal.default_platform' => 'cuentica',
            'fiscal.export_on_status' => [2],
            'fiscal.platforms.cuentica.enabled' => true,
            'fiscal.platforms.cuentica.base_url' => 'https://api.cuentica.com',
        ]);
    }

    public function test_button_endpoint_exports_invoice_to_cuentica(): void
    {
        $this->fakeCuentica();

        [$user, $invoice] = $this->makeUserWithExportableInvoice();
        $user->givePermissionTo('invoice.edit');

        $this->actingAs($user)
            ->post(route('invoice.fiscal-export', $invoice->id))
            ->assertRedirect(route('invoice.show', $invoice->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fiscal_exports', [
            'invoice_id' => $invoice->id,
            'platform' => 'cuentica',
            'status' => FiscalExport::STATUS_EXPORTED,
            'external_id' => '12345',
        ]);
    }

    public function test_button_endpoint_forbidden_without_invoice_edit_permission(): void
    {
        $this->fakeCuentica();

        [$user, $invoice] = $this->makeUserWithExportableInvoice();

        $this->actingAs($user)
            ->post(route('invoice.fiscal-export', $invoice->id))
            ->assertForbidden();

        $this->assertDatabaseMissing('fiscal_exports', ['invoice_id' => $invoice->id]);
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function makeUserWithExportableInvoice(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $team->setSetting('cuentica_api_token', 'test-token');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Acme SL',
            'email' => 'billing@acme.test',
            'address' => 'Calle Mayor 1',
            'postal_code' => '28013',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => 'ES',
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'Acme SL',
            'tax_status_type_id' => 1,
            'identification_number' => 'B12345678',
            'address' => 'Calle Mayor 1',
            'postal_code' => '28013',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => 'ES',
            'status' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'INV-2026-0001',
            'date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Plan Pro',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);

        return [$user, $invoice->fresh()];
    }

    private function fakeCuentica(): void
    {
        Http::fake(function (Request $request)
        {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'POST' && $path === '/invoice')
            {
                return Http::response(['id' => 12345, 'number' => '0001'], 200);
            }

            if ($request->method() === 'POST' && $path === '/customer')
            {
                return Http::response(['id' => 999], 200);
            }

            return Http::response([], 200);
        });
    }
}
