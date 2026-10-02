<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\SubscriptionScheduleReader;
use PHPUnit\Framework\TestCase;

class SubscriptionScheduleReaderTest extends TestCase
{
    public function test_not_started_schedule_maps_to_a_subscription_row(): void
    {
        $mapped = (new SubscriptionScheduleReader)->mapNotStarted([
            'id' => 'sub_sched_1UIWugRwN51ygFdezw0mwwr5',
            'status' => 'not_started',
            'customer' => 'cus_TTFOX7NVHkJwYC',
            'phases' => [
                [
                    'start_date' => 1817071200,
                    'end_date' => 1848693600,
                    'description' => 'Dominio CLEANUPBUENOSAIRES.COM',
                    'items' => [
                        [
                            'quantity' => 1,
                            'price' => [
                                'currency' => 'eur',
                                'unit_amount' => 2400,
                                'recurring' => [
                                    'interval' => 'year',
                                    'interval_count' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($mapped);
        $this->assertSame('sub_sched_1UIWugRwN51ygFdezw0mwwr5', $mapped['stripe_id']);
        $this->assertSame('not_started', $mapped['status']);
        $this->assertSame('Dominio CLEANUPBUENOSAIRES.COM', $mapped['raw_payload']['description']);
        $this->assertSame(24.0, $mapped['amount_total']);
        $this->assertSame('EUR', $mapped['price_currency']);
        $this->assertSame('year', $mapped['plan_interval']);
        $this->assertSame('2027-07-31T22:00:00+00:00', $mapped['current_period_end']);
        $this->assertSame('charge_automatically', $mapped['collection_method']);
    }

    public function test_schedule_keeps_an_explicit_collection_method(): void
    {
        $mapped = (new SubscriptionScheduleReader)->mapNotStarted([
            'id' => 'sub_sched_invoice',
            'status' => 'not_started',
            'default_settings' => ['collection_method' => 'send_invoice'],
            'phases' => [
                [
                    'start_date' => 1817071200,
                    'items' => [
                        ['quantity' => 1, 'price' => ['currency' => 'eur', 'unit_amount' => 100]],
                    ],
                ],
            ],
        ]);

        $this->assertSame('send_invoice', $mapped['collection_method']);
    }

    public function test_started_schedule_is_ignored(): void
    {
        $mapped = (new SubscriptionScheduleReader)->mapNotStarted([
            'id' => 'sub_sched_active',
            'status' => 'active',
            'phases' => [
                ['start_date' => 1817071200, 'items' => []],
            ],
        ]);

        $this->assertNull($mapped);
    }
}
