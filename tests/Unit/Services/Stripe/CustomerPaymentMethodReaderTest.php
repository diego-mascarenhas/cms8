<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\CustomerPaymentMethodReader;
use PHPUnit\Framework\TestCase;

class CustomerPaymentMethodReaderTest extends TestCase
{
    public function test_card_maps_brand_last4_and_expiry(): void
    {
        $mapped = (new CustomerPaymentMethodReader)->mapCard([
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 8,
                'exp_year' => 2028,
            ],
        ]);

        $this->assertSame([
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 8,
            'exp_year' => 2028,
        ], $mapped);
    }

    public function test_missing_card_is_ignored(): void
    {
        $this->assertNull((new CustomerPaymentMethodReader)->mapCard([]));
    }
}
