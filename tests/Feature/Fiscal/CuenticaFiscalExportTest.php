<?php

namespace Tests\Feature\Fiscal;

use App\Models\Enterprise;
use App\Models\EnterpriseBillingAddress;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Team;
use App\Services\Fiscal\FiscalExportService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTaxStatusTypeSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CuenticaFiscalExportTest extends TestCase
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
            CurrencySeeder::class,
        ]);

        config([
            'fiscal.enabled' => true,
            'fiscal.default_platform' => 'cuentica',
            'fiscal.export_on_status' => [2],
            'fiscal.rectify_on_status' => [3, 4],
            'fiscal.platforms.cuentica.enabled' => true,
            'fiscal.platforms.cuentica.base_url' => 'https://api.cuentica.com',
        ]);
    }

    public function test_exports_paid_invoice_to_cuentica_and_is_idempotent(): void
    {
        $this->fakeCuentica();

        $invoice = $this->makePaidInvoiceWithFiscalData();

        $service = app(FiscalExportService::class);
        $export = $service->export($invoice);

        $this->assertInstanceOf(FiscalExport::class, $export);
        $this->assertSame(FiscalExport::STATUS_EXPORTED, $export->status);
        $this->assertSame('12345', $export->external_id);
        $this->assertSame('cuentica', $export->platform);

        $this->assertDatabaseHas('fiscal_customer_mappings', [
            'enterprise_id' => $invoice->enterprise_id,
            'platform' => 'cuentica',
            'external_customer_id' => '999',
        ]);

        $this->assertSame(1, $this->countInvoicePosts());

        $service->export($invoice->fresh());

        $this->assertSame(1, $this->countInvoicePosts(), 'Invoice must not be issued twice in Cuéntica.');
        $this->assertSame(1, FiscalExport::query()->count());
    }

    public function test_marks_failed_when_enterprise_has_no_fiscal_data(): void
    {
        $this->fakeCuentica();

        $team = Team::factory()->create();
        $team->setSetting('cuentica_api_token', 'test-token');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Incomplete Co',
            'country' => 'ES',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'INV-NO-DATA',
            'date' => '2026-06-01',
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
        ]);

        $export = app(FiscalExportService::class)->export($invoice);

        $this->assertSame(FiscalExport::STATUS_FAILED, $export->status);
        $this->assertStringContainsString('missing fiscal data', (string) $export->error_message);
        $this->assertSame(0, $this->countInvoicePosts());
    }

    public function test_does_not_export_when_team_has_no_cuentica_token(): void
    {
        $this->fakeCuentica();

        $invoice = $this->makePaidInvoiceWithFiscalData(withToken: false);

        $service = app(FiscalExportService::class);

        $this->assertFalse($service->isEligible($invoice));

        $export = $service->export($invoice);

        $this->assertSame(FiscalExport::STATUS_SKIPPED, $export->status);
        $this->assertSame(0, $this->countInvoicePosts());
    }

    public function test_skips_invoice_that_is_not_in_exportable_status(): void
    {
        $this->fakeCuentica();

        $invoice = $this->makePaidInvoiceWithFiscalData();
        $invoice->status = 1;
        $invoice->save();

        $export = app(FiscalExportService::class)->export($invoice->fresh());

        $this->assertSame(FiscalExport::STATUS_SKIPPED, $export->status);
        $this->assertSame(0, $this->countInvoicePosts());
    }

    private function makePaidInvoiceWithFiscalData(bool $withToken = true): Invoice
    {
        $team = Team::factory()->create();

        if ($withToken)
        {
            $team->setSetting('cuentica_api_token', 'test-token');
        }

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

        return $invoice->fresh();
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

            // GET /customer (lookup by tax_id) -> no match
            return Http::response([], 200);
        });
    }

    private function countInvoicePosts(): int
    {
        return Http::recorded(function (Request $request)
        {
            return $request->method() === 'POST'
                && parse_url($request->url(), PHP_URL_PATH) === '/invoice';
        })->count();
    }
}
