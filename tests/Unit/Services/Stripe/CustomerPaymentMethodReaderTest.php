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

    public function test_listed_cards_keep_the_invoice_default_first(): void
    {
        $cards = (new CustomerPaymentMethodReader)->listedCards([
            [
                'id' => 'pm_old',
                'card' => ['brand' => 'visa', 'last4' => '1111', 'exp_month' => 1, 'exp_year' => 2027],
            ],
            [
                'id' => 'pm_default',
                'card' => ['brand' => 'mastercard', 'last4' => '4242', 'exp_month' => 8, 'exp_year' => 2031],
            ],
        ], 'pm_default');

        $this->assertSame('pm_default', $cards[0]['id']);
        $this->assertTrue($cards[0]['default']);
        $this->assertSame('pm_old', $cards[1]['id']);
        $this->assertFalse($cards[1]['default']);
    }

    public function test_listed_cards_mark_the_first_when_none_is_default(): void
    {
        $cards = (new CustomerPaymentMethodReader)->listedCards([
            [
                'id' => 'pm_only',
                'card' => ['brand' => 'visa', 'last4' => '8936', 'exp_month' => 8, 'exp_year' => 2031],
            ],
        ], '');

        $this->assertTrue($cards[0]['default']);
    }
}
