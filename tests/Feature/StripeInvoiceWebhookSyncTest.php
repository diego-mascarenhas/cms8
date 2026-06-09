<?php

namespace Tests\Feature;

use App\Jobs\ProcessStripeInvoiceWebhookJob;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use App\Services\Billing\StripeInvoiceWebhookSyncService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Database\Seeders\PaymentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeInvoiceWebhookSyncTest extends TestCase
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

    public function test_webhook_job_imports_paid_invoice_and_creates_payment(): void
    {
        $team = Team::factory()->create();
        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 1,
            'status_id' => 1,
            'name' => 'Webhook Client',
            'code' => 'cus_webhook123',
        ]);

        $payload = [
            'id' => 'in_webhook_paid_001',
            'number' => '0005-0700',
            'status' => 'paid',
            'currency' => 'eur',
            'amount_due' => 7500,
            'amount_paid' => 7500,
            'amount_remaining' => 0,
            'total' => 7500,
            'paid' => true,
            'created' => strtotime('2026-04-01 10:00:00'),
            'due_date' => strtotime('2026-04-15 23:59:59'),
            'customer' => 'cus_webhook123',
            'status_transitions' => ['paid_at' => strtotime('2026-04-05 14:30:00')],
        ];

        $job = new ProcessStripeInvoiceWebhookJob($payload, 'invoice.paid');
        $job->handle(app(StripeInvoiceWebhookSyncService::class));

        $invoice = Invoice::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_reference_id', 'in_webhook_paid_001')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame($enterprise->id, $invoice->enterprise_id);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(2, (int) $invoice->status);

        $payment = Payment::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('source_reference_id', 'stripe-invoice:in_webhook_paid_001')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(75.0, (float) $payment->amount);
    }

    public function test_invoice_paid_webhook_dispatches_sync_job(): void
    {
        Queue::fake();

        $payload = [
            'data' => [
                'object' => [
                    'id' => 'in_dispatch_test',
                    'status' => 'paid',
                    'paid' => true,
                    'customer' => 'cus_any',
                ],
            ],
        ];

        $controller = app(\App\Http\Controllers\StripeWebhookController::class);
        $controller->handleInvoicePaid($payload);

        Queue::assertPushed(ProcessStripeInvoiceWebhookJob::class, function (ProcessStripeInvoiceWebhookJob $job): bool
        {
            return $job->eventType === 'invoice.paid'
                && ($job->invoicePayload['id'] ?? null) === 'in_dispatch_test';
        });
    }
}
