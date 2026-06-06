<?php

namespace Tests\Unit\Services\Billing;

use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeInvoiceCoreMapper;
use App\Services\Billing\StripeInvoiceSyncUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeInvoiceSyncUpserterTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_from_paid_stripe_payload_updates_invoice_sync(): void
    {
        $team = Team::factory()->create();

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_1THBGlRwN51ygFdefviQfPFz',
            'status' => 'open',
            'currency' => 'eur',
            'amount_due' => 92.93,
            'amount_paid' => 0,
            'amount_remaining' => 92.93,
            'total' => 92.93,
            'paid' => false,
            'last_synced_at' => now()->subMonth(),
        ]);

        $sync = app(StripeInvoiceSyncUpserter::class)->upsertFromPayload($team->id, [
            'id' => 'in_1THBGlRwN51ygFdefviQfPFz',
            'number' => '0005-0659',
            'status' => 'paid',
            'currency' => 'eur',
            'amount_due' => 9293,
            'amount_paid' => 9293,
            'amount_remaining' => 0,
            'total' => 9293,
            'paid' => true,
            'created' => strtotime('2026-03-31 23:00:23'),
            'due_date' => strtotime('2026-04-10 23:59:59'),
            'customer' => 'cus_TcGQnwECCiJNh2',
        ]);

        $this->assertInstanceOf(InvoiceSync::class, $sync);
        $this->assertSame('paid', $sync->status);
        $this->assertTrue($sync->paid);
        $this->assertSame(92.93, (float) $sync->amount_paid);
        $this->assertSame(0.0, (float) $sync->amount_remaining);

        $core = app(StripeInvoiceCoreMapper::class)->mapFromInvoiceSync($sync->fresh());
        $this->assertSame(2, $core['status']);
        $this->assertSame(0.0, $core['balance']);
    }
}
