<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Models\Team;
use App\Services\Billing\StripeCollectedInvoicePaymentReconciliationService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCollectedInvoicePaymentReconciliationServiceTest extends TestCase
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

    public function test_refreshes_core_from_paid_invoice_sync_before_creating_payment(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Stale Core Client',
            'code' => 'cus_stale_core',
        ]);

        $externalId = 'in_stale_core_001';

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0900',
            'date' => '2026-03-31',
            'due_date' => '2026-04-10',
            'gross_amount' => 40.00,
            'discount' => null,
            'total_amount' => 40.00,
            'balance' => 40.00,
            'status' => 1,
            'source_provider' => 'stripe',
            'source_reference_id' => $externalId,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => $externalId,
            'customer_id' => 'cus_stale_core',
            'status' => 'paid',
            'currency' => 'eur',
            'amount_due' => 40.00,
            'amount_paid' => 40.00,
            'amount_remaining' => 0,
            'total' => 40.00,
            'paid' => true,
            'raw_payload' => ['status_transitions' => ['paid_at' => strtotime('2026-04-05 12:00:00')]],
            'last_synced_at' => now(),
        ]);

        $stats = app(StripeCollectedInvoicePaymentReconciliationService::class)->reconcile(
            teamId: $team->id,
            limit: 10,
        );

        $invoice->refresh();

        $this->assertSame(1, $stats['core_refreshed']);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame('Cobrada', $invoice->status_label);
        $this->assertSame(1, $stats['from_invoice_sync']);
    }

    public function test_creates_payment_from_paid_invoice_sync_when_invoice_has_no_payments(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Test Client',
            'code' => 'cus_test123',
        ]);

        $externalId = 'in_reconcile_test_001';

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0659',
            'date' => '2026-03-31',
            'due_date' => '2026-04-10',
            'gross_amount' => 92.93,
            'discount' => null,
            'total_amount' => 92.93,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => $externalId,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => $externalId,
            'customer_id' => 'cus_test123',
            'status' => 'paid',
            'currency' => 'eur',
            'amount_due' => 92.93,
            'amount_paid' => 92.93,
            'amount_remaining' => 0,
            'total' => 92.93,
            'paid' => true,
            'raw_payload' => ['status_transitions' => ['paid_at' => strtotime('2026-04-05 12:00:00')]],
            'last_synced_at' => now(),
        ]);

        $stats = app(StripeCollectedInvoicePaymentReconciliationService::class)->reconcile(
            teamId: $team->id,
            limit: 10,
        );

        $this->assertSame(1, $stats['matched']);
        $this->assertSame(1, $stats['from_invoice_sync']);

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', 'stripe-invoice:'.$externalId)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(92.93, (float) $payment->amount);
    }

    public function test_imports_payment_from_payment_sync_when_charge_exists(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Charge Client',
            'code' => 'cus_charge123',
        ]);

        $externalId = 'in_reconcile_test_002';

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => '0005-0660',
            'date' => '2026-03-31',
            'due_date' => '2026-04-10',
            'gross_amount' => 50.00,
            'discount' => null,
            'total_amount' => 50.00,
            'balance' => 0,
            'status' => 2,
            'source_provider' => 'stripe',
            'source_reference_id' => $externalId,
        ]);

        PaymentSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'ch_test_charge_001',
            'invoice_external_id' => $externalId,
            'customer_id' => 'cus_charge123',
            'status' => 'succeeded',
            'currency' => 'eur',
            'amount_net_cents' => 5000,
            'charge_created_at' => now(),
            'last_synced_at' => now(),
        ]);

        $reconciled = app(StripeCollectedInvoicePaymentReconciliationService::class)->reconcileInvoice($invoice);

        $this->assertTrue($reconciled);

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_reference_id', 'ch_test_charge_001')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(50.0, (float) $payment->amount);
    }
}
