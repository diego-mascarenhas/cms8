<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\Finance\StripeInvoicePdfRefresher;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class HaciendaPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_uses_the_selected_period_and_document_links(): void
    {
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'REVISION ALPHA S.L.'])->save();
        $hash = $team->haciendaShareHash();
        $currencyId = Currency::query()->where('code', 'EUR')->value('id');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SL',
            'type_id' => 1,
            'status_id' => 1,
            'country' => 'ES',
        ]);

        $sale = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1100',
            'date' => '2024-05-10',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_public_sale',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_public_sale',
            'number' => '0005-1100',
            'status' => 'paid',
            'currency' => 'eur',
            'total' => 121,
            'paid' => true,
            'invoice_pdf' => 'https://files.stripe.com/factura-0005-1100.pdf',
            'last_synced_at' => now(),
        ]);

        $purchase = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'ES-7788',
            'date' => '2024-05-12',
            'gross_amount' => 50,
            'total_amount' => 60.5,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId,
            'source_provider' => 'manual',
        ]);

        $account = PaymentAccount::query()->create([
            'team_id' => $team->id,
            'code' => 'BANK',
            'name' => 'Banco',
            'status' => 1,
        ]);

        Payment::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'transaction_type' => TransactionType::EXPENSE,
            'date' => '2024-05-12',
            'invoice_id' => $purchase->id,
            'account_id' => $account->id,
            'type_id' => 1,
            'amount' => 60.5,
            'remarks' => 'Documento: https://cms8.test/storage/expenses/team/factura-es-7788.pdf',
            'status' => 2,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1001',
            'date' => '2024-05-20',
            'gross_amount' => 10,
            'total_amount' => 12.1,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId,
            'source_provider' => 'manual',
        ]);

        Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'FUERA-PERIODO',
            'date' => '2024-06-01',
            'gross_amount' => 10,
            'total_amount' => 12.1,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId,
            'source_provider' => 'manual',
        ]);

        $this->get(route('hacienda.public', ['hash' => 'missing-hash']))
            ->assertNotFound();

        $this->get(route('hacienda.public', [
            'hash' => $hash,
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))
            ->assertOk()
            ->assertSee('value="m:1"', false)
            ->assertSee('value="q:3"', false)
            ->assertSee('Comprobante', false)
            ->assertSee('text-end', false)
            ->assertSee('Estado', false)
            ->assertSee('0005-1100', false)
            ->assertSee('0005-1001', false)
            ->assertSee('ES-7788', false)
            ->assertSee('/invoice/'.$sale->id.'/file', false)
            ->assertSee('/invoice/'.$purchase->id.'/file', false)
            ->assertSee('fill="#E31C23"', false)
            ->assertDontSee('M14 3v4a1 1 0 0 0 1 1h4', false)
            ->assertDontSee('ti ti-pdf', false)
            ->assertDontSee('btn-icon', false)
            ->assertDontSee('>https://files.stripe.com/factura-0005-1100.pdf<', false)
            ->assertSee(__('Sales invoices'), false)
            ->assertSee(__('Purchase invoices'), false)
            ->assertSee('/export?vat_year=2024', false)
            ->assertSee('vat_period=m', false)
            ->assertDontSee('FUERA-PERIODO', false);

        $export = $this->get(route('hacienda.public.export', [
            'hash' => $hash,
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]));
        $export->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export->baseResponse->getFile()->getPathname()) === true);
        $saleCsv = '';
        $purchaseCsv = '';
        for ($index = 0; $index < $zip->numFiles; $index++)
        {
            $name = (string) $zip->getNameIndex($index);
            $contents = (string) $zip->getFromIndex($index);
            if (str_contains($name, 'venta') && ! str_contains($name, 'notas-credito'))
            {
                $saleCsv = $contents;
            }
            if (str_contains($name, 'compra') && ! str_contains($name, 'notas-credito'))
            {
                $purchaseCsv = $contents;
            }
        }
        $zip->close();

        $page = $this->get(route('hacienda.public', [
            'hash' => $hash,
            'vat_year' => 2024,
            'vat_period' => 'm:5',
        ]))->getContent();
        $this->assertLessThan(
            strpos((string) $page, '0005-1100'),
            strpos((string) $page, '0005-1001'),
        );

        $this->assertStringContainsString('0005-1100', $saleCsv);
        $this->assertLessThan(
            strpos($saleCsv, '0005-1100'),
            strpos($saleCsv, '0005-1001'),
        );
        $this->assertStringContainsString('/invoice/'.$sale->id.'/file', $saleCsv);
        $this->assertStringNotContainsString('invoice.stripe.com', $saleCsv);
        $this->assertStringContainsString('ES-7788', $purchaseCsv);
        $this->assertStringContainsString('/invoice/'.$purchase->id.'/file', $purchaseCsv);
        $this->assertStringNotContainsString('FUERA-PERIODO', $saleCsv.$purchaseCsv);
        $this->assertSame($sale->id, Invoice::withoutGlobalScopes()->where('number', '0005-1100')->value('id'));

        Http::fake([
            'https://files.stripe.com/*' => Http::response('pdf-bytes', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="factura-0005-1100.pdf"',
            ]),
            'https://cms8.test/*' => Http::response('%PDF-purchase', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="factura-es-7788.pdf"',
            ]),
        ]);

        $this->get(route('hacienda.public.file', ['hash' => $hash, 'invoice' => $sale->id]))
            ->assertOk()
            ->assertDownload('REVISION ALPHA S.L. - factura-0005-1100 - Cliente SL.pdf');

        $this->get(route('hacienda.public.file', ['hash' => $hash, 'invoice' => $purchase->id]))
            ->assertOk()
            ->assertDownload('Cliente SL - factura-es-7788.pdf');

        $this->get(route('hacienda.public.file', ['hash' => $hash, 'invoice' => 999999]))
            ->assertNotFound();
    }

    public function test_expired_stripe_page_is_replaced_with_a_fresh_pdf(): void
    {
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $team->forceFill(['name' => 'REVISION ALPHA S.L.'])->save();
        $hash = $team->haciendaShareHash();
        $currencyId = Currency::query()->where('code', 'EUR')->value('id');

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente SL',
            'type_id' => 1,
            'status_id' => 1,
            'country' => 'ES',
        ]);

        $sale = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-1100',
            'date' => '2024-05-10',
            'gross_amount' => 100,
            'total_amount' => 121,
            'balance' => 0,
            'status' => 2,
            'currency_id' => $currencyId,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_expired_link',
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_expired_link',
            'number' => '0005-1100',
            'status' => 'paid',
            'currency' => 'eur',
            'total' => 121,
            'paid' => true,
            'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_test/expired',
            'last_synced_at' => now(),
        ]);

        $refresher = $this->mock(StripeInvoicePdfRefresher::class);
        $refresher->shouldReceive('freshPdfUrl')->andReturn('https://files.stripe.com/fresh-factura.pdf');

        Http::fake([
            'https://files.stripe.com/fresh-factura.pdf' => Http::response('%PDF-fresh', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Invoice-0005-1100.pdf"',
            ]),
            'https://invoice.stripe.com/*' => Http::response('This link expired', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $fresh = $this->get(route('hacienda.public.file', ['hash' => $hash, 'invoice' => $sale->id]));
        $fresh->assertOk();
        $fresh->assertDownload('REVISION ALPHA S.L. - Invoice-0005-1100 - Cliente SL.pdf');
        $this->assertStringStartsWith('%PDF', $fresh->streamedContent());
    }
}
