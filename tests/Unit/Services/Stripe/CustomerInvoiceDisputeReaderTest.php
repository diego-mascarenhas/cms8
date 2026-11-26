<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\CustomerInvoiceDisputeReader;
use PHPUnit\Framework\TestCase;

class CustomerInvoiceDisputeReaderTest extends TestCase
{
    public function test_lost_and_open_disputes_are_kept_and_won_disputes_are_not(): void
    {
        $statuses = (new CustomerInvoiceDisputeReader)->statusesFromInvoices([
            (object) [
                'id' => 'in_lost',
                'charge' => (object) [
                    'disputed' => true,
                    'dispute' => (object) ['status' => 'lost'],
                ],
            ],
            (object) [
                'id' => 'in_won',
                'charge' => (object) [
                    'disputed' => true,
                    'dispute' => (object) ['status' => 'won'],
                ],
            ],
            (object) [
                'id' => 'in_open',
                'charge' => (object) [
                    'disputed' => true,
                    'dispute' => 'dp_123',
                ],
            ],
            (object) [
                'id' => 'in_paid',
                'charge' => (object) [
                    'disputed' => false,
                    'dispute' => null,
                ],
            ],
            (object) [
                'id' => 'in_closed',
                'charge' => (object) [
                    'disputed' => true,
                    'dispute' => (object) ['status' => 'warning_closed'],
                ],
            ],
        ]);

        $this->assertSame([
            'in_lost' => 'lost',
            'in_open' => 'open',
        ], $statuses);
    }
}
