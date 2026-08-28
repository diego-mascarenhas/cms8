<?php

namespace Tests\Feature;

use App\Actions\Subscriptions\SyncStripeSubscriptions;
use App\Models\InvoiceSync;
use App\Models\ServiceSync;
use App\Models\SubscriptionChange;
use App\Models\Team;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncStripeSubscriptionsSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_subscription_change_and_latest_invoice(): void
    {
        $team = Team::factory()->create();

        $payload = $this->subscriptionPayload('sub_seq_1', 'in_seq_1');
        $stripe = Mockery::mock(StripeSubscriptionService::class);
        $stripe->shouldReceive('subscriptions')->once()->andReturn($this->yieldPayloads([$payload]));

        $processed = (new SyncStripeSubscriptions($stripe))->handle($team);

        $this->assertSame(1, $processed);

        $subscription = ServiceSync::query()->where('stripe_id', 'sub_seq_1')->first();
        $this->assertNotNull($subscription);
        $this->assertSame($team->id, (int) $subscription->team_id);

        $this->assertTrue(
            SubscriptionChange::query()->where('subscription_id', $subscription->id)->exists(),
        );

        $invoice = InvoiceSync::query()->where('external_id', 'in_seq_1')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('sub_seq_1', $invoice->stripe_subscription_id);
    }

    public function test_sync_records_a_change_when_an_existing_subscription_updates(): void
    {
        $team = Team::factory()->create();
        $subscription = ServiceSync::query()->create([
            'stripe_id' => 'sub_seq_2',
            'provider' => 'stripe',
            'type' => 'sell',
            'team_id' => $team->id,
            'status' => 'active',
            'plan_name' => 'Old plan',
        ]);

        $payload = $this->subscriptionPayload('sub_seq_2', 'in_seq_2', [
            'status' => 'past_due',
        ]);
        $stripe = Mockery::mock(StripeSubscriptionService::class);
        $stripe->shouldReceive('subscriptions')->once()->andReturn($this->yieldPayloads([$payload]));

        (new SyncStripeSubscriptions($stripe))->handle($team);

        $this->assertSame('past_due', $subscription->fresh()->status);
        $this->assertTrue(
            SubscriptionChange::query()
                ->where('subscription_id', $subscription->id)
                ->where('source', 'stripe')
                ->exists(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return \Generator<object>
     */
    private function yieldPayloads(array $payloads): \Generator
    {
        foreach ($payloads as $payload)
        {
            yield new class($payload)
            {
                public function __construct(private readonly array $payload) {}

                /**
                 * @return array<string, mixed>
                 */
                public function toArray(): array
                {
                    return $this->payload;
                }
            };
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionPayload(string $subscriptionId, string $invoiceId, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $subscriptionId,
            'status' => 'active',
            'customer' => 'cus_seq_1',
            'collection_method' => 'charge_automatically',
            'items' => [
                'data' => [[
                    'quantity' => 1,
                    'price' => [
                        'currency' => 'eur',
                        'unit_amount' => 1000,
                        'nickname' => 'Plan',
                    ],
                ]],
            ],
            'latest_invoice' => [
                'id' => $invoiceId,
                'customer' => 'cus_seq_1',
                'status' => 'paid',
                'currency' => 'eur',
                'amount_due' => 1000,
                'amount_paid' => 1000,
                'amount_remaining' => 0,
                'total' => 1000,
                'paid' => true,
                'created' => now()->timestamp,
            ],
        ], $overrides);
    }
}
