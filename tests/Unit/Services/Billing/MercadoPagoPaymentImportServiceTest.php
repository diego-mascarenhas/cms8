<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\MercadoPagoPaymentSyncUpserter;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MercadoPagoPaymentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
            PaymentTypeSeeder::class,
            CurrencySeeder::class,
        ]);
    }

    public function test_upserter_maps_approved_payment_into_payment_syncs(): void
    {
        $team = Team::factory()->create();

        $sync = app(MercadoPagoPaymentSyncUpserter::class)->upsertFromPayload($team->id, [
            'id' => 1234567890,
            'status' => 'approved',
            'currency_id' => 'ARS',
            'transaction_amount' => 1500.50,
            'transaction_amount_refunded' => 0,
            'description' => 'Suscripción mensual',
            'external_reference' => '0005-0846',
            'date_approved' => '2026-05-10T14:30:00.000-03:00',
            'payer' => [
                'id' => 998877,
                'email' => 'cliente@example.com',
            ],
        ]);

        $this->assertInstanceOf(PaymentSync::class, $sync);
        $this->assertSame('mercadopago', $sync->provider);
        $this->assertSame('1234567890', $sync->external_id);
        $this->assertSame('approved', $sync->status);
        $this->assertSame('ARS', $sync->currency);
        $this->assertSame(150050, (int) $sync->amount_cents);
        $this->assertSame(150050, (int) $sync->amount_net_cents);
        $this->assertSame('998877', $sync->customer_id);
        $this->assertSame('cliente@example.com', $sync->customer_email);
        $this->assertSame('0005-0846', $sync->invoice_external_id);
    }

    public function test_upserter_clears_payer_for_account_fund_transfers(): void
    {
        $team = Team::factory()->create();

        $sync = app(MercadoPagoPaymentSyncUpserter::class)->upsertFromPayload($team->id, [
            'id' => 999000111,
            'status' => 'approved',
            'currency_id' => 'ARS',
            'transaction_amount' => 20909.09,
            'transaction_amount_refunded' => 0,
            'description' => 'Bank Transfer',
            'operation_type' => 'account_fund',
            'collector_id' => 616106613,
            'payment_type_id' => 'bank_transfer',
            'point_of_interaction' => ['type' => 'PSP_TRANSFER'],
            'payer' => [
                'id' => '616106613',
                'email' => 'diego.mascarenhas@icloud.com',
            ],
        ]);

        $this->assertInstanceOf(PaymentSync::class, $sync);
        $this->assertNull($sync->customer_id);
        $this->assertNull($sync->customer_email);
        $this->assertTrue($sync->lacksIdentifiablePayer());
    }

    public function test_upserter_preserves_settlement_payer_on_resync(): void
    {
        $team = Team::factory()->create();
        $upserter = app(MercadoPagoPaymentSyncUpserter::class);

        $sync = $upserter->upsertFromPayload($team->id, [
            'id' => 169690439304,
            'status' => 'approved',
            'currency_id' => 'ARS',
            'transaction_amount' => 10608.16,
            'transaction_amount_refunded' => 0,
            'operation_type' => 'account_fund',
            'collector_id' => 616106613,
            'payer' => [
                'id' => '616106613',
                'email' => 'diego.mascarenhas@icloud.com',
            ],
        ]);

        $sync->mergeSettlementPayer('Hygeia Sa', 'CUIT', '30712345678');

        $resync = $upserter->upsertFromPayload($team->id, [
            'id' => 169690439304,
            'status' => 'approved',
            'currency_id' => 'ARS',
            'transaction_amount' => 10608.16,
            'transaction_amount_refunded' => 0,
            'operation_type' => 'account_fund',
            'collector_id' => 616106613,
            'description' => 'Bank Transfer updated',
            'payer' => [
                'id' => '616106613',
                'email' => 'diego.mascarenhas@icloud.com',
            ],
        ]);

        $this->assertSame((int) $sync->id, (int) $resync->id);
        $this->assertSame('Hygeia Sa', $resync->settlementPayerName());
        $this->assertSame('CUIT', $resync->settlementPayerIdType());
        $this->assertSame('30712345678', $resync->settlementPayerIdNumber());
        $this->assertSame('Bank Transfer updated', $resync->description);
        $this->assertSame('account_fund', data_get($resync->raw_payload, 'operation_type'));
        $this->assertFalse($resync->lacksIdentifiablePayer());
    }

    public function test_import_links_payment_to_invoice_by_external_reference(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente MP',
            'code' => '998877',
            'email' => 'cliente@example.com',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0846',
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'gross_amount' => 1500.50,
            'discount' => 0,
            'total_amount' => 1500.50,
            'balance' => 1500.50,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '1234567890',
            'customer_id' => '998877',
            'customer_email' => 'cliente@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'invoice_external_id' => '0005-0846',
            'description' => 'Suscripción mensual',
            'charge_created_at' => '2026-05-10 14:30:00',
            'last_synced_at' => now(),
            'raw_payload' => ['id' => 1234567890, 'external_reference' => '0005-0846'],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($invoice->id, (int) $payment->invoice_id);
    }

    public function test_import_links_unique_unpaid_invoice_by_amount(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente MP',
            'code' => '998877',
            'email' => 'cliente@example.com',
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0999',
            'date' => '2026-05-01',
            'due_date' => '2026-05-15',
            'gross_amount' => 2500.00,
            'discount' => 0,
            'total_amount' => 2500.00,
            'balance' => 2500.00,
            'status' => 1,
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '555000',
            'customer_id' => '998877',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 250000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 250000,
            'description' => 'Bank Transfer',
            'charge_created_at' => '2026-05-10 14:30:00',
            'last_synced_at' => now(),
            'raw_payload' => ['id' => 555000],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($invoice->id, (int) $payment->invoice_id);
    }

    public function test_import_skips_invoice_link_when_amount_matches_are_ambiguous(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente MP',
            'code' => '998877',
            'email' => 'cliente@example.com',
        ]);

        foreach (['2026-04-20', '2026-05-01'] as $index => $date)
        {
            Invoice::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'enterprise_id' => $enterprise->id,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => '0005-A'.$index,
                'date' => $date,
                'due_date' => $date,
                'gross_amount' => 1000.00,
                'discount' => 0,
                'total_amount' => 1000.00,
                'balance' => 1000.00,
                'status' => 1,
            ]);
        }

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '777',
            'customer_id' => '998877',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 100000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 100000,
            'charge_created_at' => '2026-05-10 14:30:00',
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertNull($payment->invoice_id);
    }

    public function test_import_creates_core_payment_for_approved_sync(): void
    {
        $team = Team::factory()->create();
        Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente MP',
            'code' => '998877',
            'email' => 'cliente@example.com',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '1234567890',
            'customer_id' => '998877',
            'customer_email' => 'cliente@example.com',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 150050,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 150050,
            'description' => 'Suscripción mensual',
            'charge_created_at' => '2026-05-10 14:30:00',
            'last_synced_at' => now(),
            'raw_payload' => ['id' => 1234567890],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('mercadopago', $payment->source_provider);
        $this->assertSame('1234567890', $payment->source_reference_id);
        $this->assertSame(1500.50, (float) $payment->amount);
        $this->assertSame(2, (int) $payment->status);
        $this->assertSame(\App\Enums\TransactionType::INCOME, $payment->transaction_type);
    }

    public function test_import_creates_core_payment_with_forced_enterprise(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente forzado',
            'code' => null,
            'email' => 'forced@example.com',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'force-ent-1',
            'customer_id' => '555',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: true,
            dryRun: false,
            forceEnterpriseId: $enterprise->id,
        );

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame($enterprise->id, (int) $payment->enterprise_id);
        $this->assertSame('555', $enterprise->fresh()->code);
    }

    public function test_import_persists_type_and_remarks_with_mp_identification_code(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Cliente notas',
            'code' => 'mp-payer-1',
            'email' => 'notas@example.com',
        ]);

        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => 'with-meta-1',
            'customer_id' => 'mp-payer-1',
            'status' => 'approved',
            'currency' => 'ARS',
            'amount_cents' => 25000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 25000,
            'description' => 'Bank Transfer',
            'last_synced_at' => now(),
            'raw_payload' => [
                'operation_type' => 'account_fund',
                'transaction_details' => [
                    'transaction_id' => '76V4MR2Z8P4VPR389DEZOL',
                ],
            ],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync(
            $sync,
            forceTypeId: 1,
            remarksOverride: '0005-0950',
        );

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(1, (int) $payment->type_id);
        $this->assertSame('Ref: 76V4MR2Z8P4VPR389DEZOL · 0005-0950', $payment->remarks);
        $this->assertSame($enterprise->id, (int) $payment->enterprise_id);
    }

    public function test_import_skips_non_approved_payments(): void
    {
        $team = Team::factory()->create();
        $sync = PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'mercadopago',
            'external_id' => '999',
            'status' => 'pending',
            'currency' => 'ARS',
            'amount_cents' => 10000,
            'amount_refunded_cents' => 0,
            'amount_net_cents' => 10000,
            'last_synced_at' => now(),
            'raw_payload' => [],
        ]);

        $payment = app(MercadoPagoPaymentImportService::class)->importFromPaymentSync($sync);

        $this->assertNull($payment);
    }
}
