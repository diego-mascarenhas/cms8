<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeInvoiceCoreImportService;
use Carbon\Carbon;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeInvoiceFiscalDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_import_uses_finalized_at_as_fiscal_date_not_draft_created(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
            'code' => 'cus_fiscal_date',
        ]);

        $row = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_fiscal_date_001',
            'customer_id' => 'cus_fiscal_date',
            'number' => '0005-0857',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 100,
            'total' => 121,
            'amount_due' => 0,
            'amount_paid' => 121,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-05-21 10:00:00',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'in_fiscal_date_001',
                'created' => strtotime('2026-05-21 10:00:00'),
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-06-04 15:00:00'),
                ],
            ],
        ]);

        $invoice = app(StripeInvoiceCoreImportService::class)->importFromSyncRow($row);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('2026-06-04', Carbon::parse($invoice->date)->toDateString());
    }

    public function test_import_keeps_the_invoice_total_when_the_customer_is_not_a_client(): void
    {
        $team = Team::factory()->create();
        $alliance = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 3,
            'status_id' => 1,
            'name' => 'Abaco Turismo',
            'code' => 'cus_alliance',
        ]);

        $row = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_alliance_paid',
            'customer_id' => 'cus_alliance',
            'number' => '0005-0906',
            'status' => 'paid',
            'currency' => 'ars',
            'subtotal' => 20909.09,
            'total' => 20909.09,
            'amount_due' => 20909.09,
            'amount_paid' => 20909.09,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-07-01 01:00:31',
            'last_synced_at' => now(),
            'raw_payload' => [
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-07-01 01:00:31 UTC'),
                ],
            ],
        ]);

        $invoice = app(StripeInvoiceCoreImportService::class)->importFromSyncRow($row);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($alliance->id, $invoice->enterprise_id);
        $this->assertSame(20909.09, (float) $invoice->total_amount);
        $this->assertSame(0.0, (float) $invoice->balance);
    }

    public function test_import_falls_back_to_created_when_finalized_at_missing(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
            'code' => 'cus_fiscal_fallback',
        ]);

        $row = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_fiscal_fallback_001',
            'customer_id' => 'cus_fiscal_fallback',
            'number' => '0005-0100',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 50,
            'total' => 50,
            'amount_due' => 0,
            'amount_paid' => 50,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-04-13 08:00:00',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'in_fiscal_fallback_001',
                'created' => strtotime('2026-04-13 08:00:00'),
            ],
        ]);

        $invoice = app(StripeInvoiceCoreImportService::class)->importFromSyncRow($row);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('2026-04-13', Carbon::parse($invoice->date)->toDateString());
    }

    public function test_later_number_keeps_finalization_date_when_the_draft_is_earlier(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente',
            'code' => 'cus_0694',
        ]);

        $earlierNumber = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_0005_0693',
            'customer_id' => 'cus_0694',
            'number' => '0005-0693',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 100,
            'total' => 121,
            'amount_due' => 0,
            'amount_paid' => 121,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-04-13 09:00:00',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'in_0005_0693',
                'created' => strtotime('2026-04-13 09:00:00'),
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-04-13 09:00:00'),
                ],
            ],
        ]);

        $laterNumberDraftedFirst = InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_0005_0694',
            'customer_id' => 'cus_0694',
            'number' => '0005-0694',
            'status' => 'paid',
            'currency' => 'eur',
            'subtotal' => 100,
            'total' => 121,
            'amount_due' => 0,
            'amount_paid' => 121,
            'amount_remaining' => 0,
            'paid' => true,
            'invoice_created_at' => '2026-02-21 16:10:45',
            'last_synced_at' => now(),
            'raw_payload' => [
                'id' => 'in_0005_0694',
                'created' => strtotime('2026-02-21 16:10:45'),
                'status_transitions' => [
                    'finalized_at' => strtotime('2026-04-14 12:09:15'),
                ],
            ],
        ]);

        $importer = app(StripeInvoiceCoreImportService::class);
        $importer->importFromSyncRow($earlierNumber);
        $importer->importFromSyncRow($laterNumberDraftedFirst);

        $ordered = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->orderBy('date')
            ->orderBy('number')
            ->pluck('number')
            ->all();

        $this->assertSame(['0005-0693', '0005-0694'], $ordered);
        $this->assertSame(
            '2026-04-14',
            Carbon::parse(
                Invoice::withoutGlobalScopes()->where('number', '0005-0694')->value('date'),
            )->toDateString(),
        );
    }
}
