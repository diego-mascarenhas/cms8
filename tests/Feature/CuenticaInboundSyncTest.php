<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\Team;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CuenticaInboundSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            EnterpriseTaxStatusTypeSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);

        config([
            'fiscal.platforms.cuentica.enabled' => true,
            'fiscal.platforms.cuentica.base_url' => 'https://api.cuentica.com',
            'fiscal.platforms.cuentica.inbound_sync.enabled' => true,
            'fiscal.platforms.cuentica.inbound_sync.page_size' => 50,
        ]);
    }

    public function test_sync_command_upserts_sale_and_purchase_rows(): void
    {
        $team = $this->teamWithToken();
        $this->fakeCuenticaListEndpoints();

        $this->artisan('cuentica:sync-invoices', [
            '--team_id' => $team->id,
            '--mode' => 'mutable',
            '--from' => '2026-06-01',
            '--to' => '2026-06-30',
            '--limit' => 10,
        ])->assertSuccessful();

        $this->assertDatabaseHas('invoice_syncs', [
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:101',
            'billing_reason' => 'cuentica_sale',
            'paid' => 1,
        ]);

        $this->assertDatabaseHas('invoice_syncs', [
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'purchase:202',
            'billing_reason' => 'cuentica_purchase',
        ]);
    }

    public function test_import_command_creates_local_sell_and_buy_invoices(): void
    {
        $team = $this->teamWithToken();
        $client = $this->makeClientEnterprise($team);
        $supplier = $this->makeSupplierEnterprise($team);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:101',
            'customer_id' => '555',
            'customer_tax_id' => 'B12345678',
            'customer_name' => $client->name,
            'number' => 'F-0001',
            'status' => 'paid',
            'billing_reason' => 'cuentica_sale',
            'currency' => 'eur',
            'subtotal' => 100,
            'tax' => 21,
            'total' => 121,
            'paid' => true,
            'invoice_created_at' => '2026-06-10',
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'purchase:202',
            'customer_id' => '777',
            'customer_tax_id' => 'A98765432',
            'customer_name' => $supplier->name,
            'number' => 'C-0001',
            'status' => 'paid',
            'billing_reason' => 'cuentica_purchase',
            'currency' => 'eur',
            'subtotal' => 50,
            'tax' => 10.5,
            'total' => 60.5,
            'paid' => true,
            'invoice_created_at' => '2026-06-11',
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--fallback-tax-id' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'operation' => 'sell',
            'source_provider' => 'cuentica',
            'source_reference_id' => 'sale:101',
            'number' => 'F-0001',
        ]);

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'operation' => 'buy',
            'source_provider' => 'cuentica',
            'source_reference_id' => 'purchase:202',
            'number' => 'C-0001',
        ]);

        $this->assertDatabaseHas('payments', [
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'transaction_type' => TransactionType::INCOME->value,
            'source_provider' => 'cuentica',
            'source_reference_id' => 'cuentica-invoice:sale:101',
            'amount' => 121,
            'status' => 2,
        ]);

        $this->assertDatabaseHas('payments', [
            'team_id' => $team->id,
            'enterprise_id' => $supplier->id,
            'transaction_type' => TransactionType::EXPENSE->value,
            'source_provider' => 'cuentica',
            'source_reference_id' => 'cuentica-invoice:purchase:202',
            'amount' => 60.5,
            'status' => 2,
        ]);
    }

    public function test_import_is_idempotent(): void
    {
        $team = $this->teamWithToken();
        $client = $this->makeClientEnterprise($team);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:101',
            'customer_tax_id' => 'B12345678',
            'status' => 'paid',
            'billing_reason' => 'cuentica_sale',
            'currency' => 'eur',
            'subtotal' => 100,
            'total' => 121,
            'paid' => true,
            'invoice_created_at' => '2026-06-10',
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--fallback-tax-id' => true,
            '--reconcile' => true,
        ])->assertSuccessful();

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--fallback-tax-id' => true,
            '--reconcile' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Invoice::withoutGlobalScopes()
            ->where('source_provider', 'cuentica')
            ->where('source_reference_id', 'sale:101')
            ->count());
    }

    public function test_import_creates_invoice_line_items_from_raw_payload(): void
    {
        $team = $this->teamWithToken();
        $client = $this->makeClientEnterprise($team);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:101',
            'customer_tax_id' => 'B12345678',
            'customer_name' => $client->name,
            'number' => 'F-0001',
            'status' => 'paid',
            'billing_reason' => 'cuentica_sale',
            'currency' => 'eur',
            'subtotal' => 30,
            'tax' => 6.3,
            'total' => 36.3,
            'paid' => true,
            'invoice_created_at' => '2026-06-10',
            'last_synced_at' => now(),
            'raw_payload' => [
                'invoice_lines' => [
                    [
                        'concept' => 'Servicio consultoría',
                        'quantity' => 1,
                        'amount' => 30,
                        'discount' => 0,
                        'tax' => 21,
                    ],
                ],
            ],
        ]);

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--fallback-tax-id' => true,
            '--reconcile' => true,
        ])->assertSuccessful();

        $invoice = Invoice::withoutGlobalScopes()
            ->where('source_reference_id', 'sale:101')
            ->firstOrFail();

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Servicio consultoría',
            'quantity' => 1,
            'unit_price' => 30,
            'discount' => 0,
            'tax_percentage' => 21,
        ]);
    }

    public function test_sync_imports_purchases_even_when_sales_hit_limit(): void
    {
        $team = $this->teamWithToken();
        $this->fakeCuenticaListEndpointsWithManySales();

        $this->artisan('cuentica:sync-invoices', [
            '--team_id' => $team->id,
            '--mode' => 'mutable',
            '--from' => '2026-06-01',
            '--to' => '2026-06-30',
            '--limit' => 3,
        ])->assertSuccessful();

        $this->assertSame(3, InvoiceSync::query()
            ->where('team_id', $team->id)
            ->where('billing_reason', 'cuentica_sale')
            ->count());

        $this->assertDatabaseHas('invoice_syncs', [
            'team_id' => $team->id,
            'external_id' => 'purchase:202',
            'billing_reason' => 'cuentica_purchase',
        ]);
    }

    public function test_import_creates_purchase_when_provider_is_scalar_id(): void
    {
        $team = $this->teamWithToken();

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'purchase:303',
            'customer_id' => '888',
            'customer_tax_id' => 'B99887766',
            'customer_name' => 'Proveedor Scalar SL',
            'number' => 'C-0009',
            'status' => 'paid',
            'billing_reason' => 'cuentica_purchase',
            'currency' => 'eur',
            'subtotal' => 40,
            'total' => 48.4,
            'paid' => true,
            'invoice_created_at' => '2026-06-12',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 303,
                'provider' => 888,
                'document_number' => 'C-0009',
                'amount_details' => [
                    'total_base' => 40,
                    'total_expense' => 48.4,
                ],
            ],
        ]);

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--auto-create-counterparty' => true,
            '--reconcile' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('invoices', [
            'team_id' => $team->id,
            'operation' => 'buy',
            'source_reference_id' => 'purchase:303',
        ]);

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'type_id' => 2,
            'code' => 'cuentica_p_888',
            'name' => 'Proveedor Scalar SL',
        ]);
    }

    public function test_import_does_not_create_payment_for_unpaid_sale(): void
    {
        $team = $this->teamWithToken();
        $client = $this->makeClientEnterprise($team);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:999',
            'customer_tax_id' => 'B12345678',
            'number' => 'F-0099',
            'status' => 'open',
            'billing_reason' => 'cuentica_sale',
            'currency' => 'eur',
            'subtotal' => 100,
            'total' => 121,
            'paid' => false,
            'amount_remaining' => 121,
            'invoice_created_at' => '2026-06-10',
            'last_synced_at' => now(),
            'raw_payload' => [
                'charges' => [['amount' => 121, 'paid' => false]],
            ],
        ]);

        $this->artisan('invoice-syncs:import-cuentica', [
            '--team_id' => $team->id,
            '--fallback-tax-id' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('invoices', [
            'source_reference_id' => 'sale:999',
        ]);

        $this->assertSame(0, Payment::withoutGlobalScopes()
            ->where('source_provider', 'cuentica')
            ->where('source_reference_id', 'cuentica-invoice:sale:999')
            ->count());
    }

    public function test_reconcile_command_creates_missing_payments_for_existing_invoices(): void
    {
        $team = $this->teamWithToken();
        $client = $this->makeClientEnterprise($team);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'F-0001',
            'date' => '2026-06-10',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'cuentica',
            'source_reference_id' => 'sale:101',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'cuentica',
            'external_id' => 'sale:101',
            'customer_tax_id' => 'B12345678',
            'status' => 'paid',
            'billing_reason' => 'cuentica_sale',
            'currency' => 'eur',
            'total' => 121,
            'paid' => true,
            'invoice_created_at' => '2026-06-10',
            'last_synced_at' => now(),
            'raw_payload' => [
                'charges' => [['amount' => 121, 'paid' => true, 'date' => '2026-06-10']],
            ],
        ]);

        $this->artisan('invoices:reconcile-cuentica-collected-payments', [
            '--team_id' => $team->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('payments', [
            'team_id' => $team->id,
            'invoice_id' => $invoice->id,
            'transaction_type' => TransactionType::INCOME->value,
            'source_reference_id' => 'cuentica-invoice:sale:101',
            'amount' => 121,
        ]);
    }

    private function teamWithToken(): Team
    {
        $team = Team::factory()->create();
        $team->setSetting('cuentica_api_token', 'test-token');
        $team->setSetting('cuentica_inbound_sync_enabled', true);

        return $team;
    }

    private function makeClientEnterprise(Team $team): Enterprise
    {
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente Test SL',
            'email' => 'cliente@test.test',
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'Cliente Test SL',
            'tax_status_type_id' => 1,
            'identification_number' => 'B12345678',
            'address' => 'Calle 1',
            'postal_code' => '28001',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => 'ES',
            'status' => 1,
        ]);

        return $enterprise;
    }

    private function makeSupplierEnterprise(Team $team): Enterprise
    {
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 1,
            'name' => 'Proveedor Test SL',
            'email' => 'proveedor@test.test',
        ]);

        EnterpriseBillingAddress::query()->create([
            'enterprise_id' => $enterprise->id,
            'name' => 'Proveedor Test SL',
            'tax_status_type_id' => 1,
            'identification_number' => 'A98765432',
            'address' => 'Calle 2',
            'postal_code' => '28002',
            'locality' => 'Madrid',
            'province' => 'Madrid',
            'country' => 'ES',
            'status' => 1,
        ]);

        return $enterprise;
    }

    private function fakeCuenticaListEndpoints(): void
    {
        Http::fake(function (Request $request)
        {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'GET' && $path === '/invoice')
            {
                return Http::response([
                    [
                        'id' => 101,
                        'date' => '2026-06-10',
                        'issued' => true,
                        'invoice_serie' => 'F',
                        'invoice_number' => 1,
                        'customer' => [
                            'id' => 555,
                            'tax_id' => 'B12345678',
                            'business_name' => 'Acme SL',
                            'email' => 'billing@acme.test',
                            'country_code' => 'ES',
                        ],
                        'amount_details' => [
                            'total_base' => 100,
                            'total_invoice' => 121,
                        ],
                        'charges' => [['amount' => 121, 'paid' => true]],
                    ],
                ], 200);
            }

            if ($request->method() === 'GET' && $path === '/expense')
            {
                return Http::response([
                    [
                        'id' => 202,
                        'date' => '2026-06-11',
                        'draft' => false,
                        'document_number' => 'C-0001',
                        'provider' => [
                            'id' => 777,
                            'tax_id' => 'A98765432',
                            'business_name' => 'Proveedor SL',
                        ],
                        'amount_details' => [
                            'total_base' => 50,
                            'total_expense' => 60.5,
                        ],
                        'payments' => [['amount' => 60.5, 'paid' => true]],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });
    }

    private function fakeCuenticaListEndpointsWithManySales(): void
    {
        Http::fake(function (Request $request)
        {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'GET' && $path === '/invoice')
            {
                return Http::response([
                    ['id' => 1001, 'date' => '2026-06-01', 'issued' => true, 'amount_details' => ['total_base' => 10, 'total_invoice' => 12.1], 'charges' => [['amount' => 12.1, 'paid' => true]]],
                    ['id' => 1002, 'date' => '2026-06-02', 'issued' => true, 'amount_details' => ['total_base' => 10, 'total_invoice' => 12.1], 'charges' => [['amount' => 12.1, 'paid' => true]]],
                    ['id' => 1003, 'date' => '2026-06-03', 'issued' => true, 'amount_details' => ['total_base' => 10, 'total_invoice' => 12.1], 'charges' => [['amount' => 12.1, 'paid' => true]]],
                    ['id' => 1004, 'date' => '2026-06-04', 'issued' => true, 'amount_details' => ['total_base' => 10, 'total_invoice' => 12.1], 'charges' => [['amount' => 12.1, 'paid' => true]]],
                    ['id' => 1005, 'date' => '2026-06-05', 'issued' => true, 'amount_details' => ['total_base' => 10, 'total_invoice' => 12.1], 'charges' => [['amount' => 12.1, 'paid' => true]]],
                ], 200);
            }

            if ($request->method() === 'GET' && $path === '/expense')
            {
                return Http::response([
                    [
                        'id' => 202,
                        'date' => '2026-06-11',
                        'draft' => false,
                        'document_number' => 'C-0001',
                        'provider' => 777,
                        'amount_details' => [
                            'total_base' => 50,
                            'total_expense' => 60.5,
                        ],
                        'payments' => [['amount' => 60.5, 'paid' => true]],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });
    }
}
