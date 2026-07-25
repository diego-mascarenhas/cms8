<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use Carbon\Carbon;
use Tests\TestCase;

class InvoiceStatusLabelTest extends TestCase
{
    public function test_status_labels_use_payment_friendly_names(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $invoice = new Invoice;

        $invoice->status = 1;
        $invoice->due_date = '2026-06-20';
        $invoice->balance = 100;
        $this->assertSame('Pendiente', $invoice->status_label);

        $invoice->status = 2;
        $invoice->balance = 0;
        $this->assertSame('Cobrada', $invoice->status_label);

        $invoice->status = 9;
        $invoice->balance = 100;
        $this->assertSame('Borrador', $invoice->status_label);
    }

    public function test_legacy_printed_status_with_balance_uses_collection_state(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $overdue = new Invoice([
            'status' => 2,
            'due_date' => '2026-06-01',
            'balance' => 164224.83,
        ]);

        $this->assertSame('Vencida', $overdue->status_label);
        $this->assertStringContainsString('bg-label-danger', $overdue->status_badge);

        $pending = new Invoice([
            'status' => 2,
            'due_date' => '2026-06-20',
            'balance' => 100,
        ]);

        $this->assertSame('Pendiente', $pending->status_label);
        $this->assertStringContainsString('bg-label-warning', $pending->status_badge);
    }

    public function test_pending_invoice_past_due_shows_vencida(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $invoice = new Invoice([
            'status' => 1,
            'due_date' => '2026-06-01',
            'balance' => 50,
        ]);

        $this->assertTrue($invoice->isOverdue());
        $this->assertSame('Vencida', $invoice->status_label);
        $this->assertStringContainsString('bg-label-danger', $invoice->status_badge);
    }

    public function test_status_badges_use_expected_colors(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $pending = new Invoice([
            'status' => 1,
            'due_date' => '2026-06-20',
            'balance' => 50,
        ]);

        $collected = new Invoice(['status' => 2, 'balance' => 0]);

        $this->assertStringContainsString('bg-label-warning', $pending->status_badge);
        $this->assertStringContainsString('Pendiente', $pending->status_badge);
        $this->assertStringContainsString('bg-label-success', $collected->status_badge);
        $this->assertStringContainsString('Cobrada', $collected->status_badge);
    }

    public function test_bonificada_status_never_shows_as_pending(): void
    {
        Carbon::setTestNow('2026-06-06 12:00:00');

        $bonificada = new Invoice([
            'status' => 5,
            'due_date' => '2005-03-01',
            'balance' => 0,
        ]);

        $this->assertSame('Bonificada', $bonificada->status_label);
        $this->assertStringContainsString('Bonificada', $bonificada->status_badge);
        $this->assertStringNotContainsString('Pendiente', $bonificada->status_badge);
    }

    public function test_stripe_uncollectible_status_shows_as_incobrable_not_error(): void
    {
        $invoice = new Invoice([
            'status' => 7,
            'due_date' => '2026-06-01',
            'balance' => 39962,
        ]);

        $this->assertSame('Incobrable', $invoice->status_label);
        $this->assertStringContainsString('Incobrable', $invoice->status_badge);
        $this->assertStringContainsString('bg-label-warning', $invoice->status_badge);
        $this->assertStringNotContainsString('Error', $invoice->status_badge);
    }
}
