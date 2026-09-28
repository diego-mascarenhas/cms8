<?php

namespace Tests\Unit\Services;

use App\Services\TeamStripeCustomerService;
use PHPUnit\Framework\TestCase;

class TeamStripeCustomerServiceTest extends TestCase
{
    public function test_finds_customer_when_the_email_matches(): void
    {
        $service = new class extends TeamStripeCustomerService
        {
            protected function stripeCustomersWithEmail(string $email): iterable
            {
                return [
                    (object) ['id' => 'cus_other', 'email' => 'other@example.com', 'deleted' => false],
                    (object) ['id' => 'cus_match', 'email' => 'Leticia@example.com', 'deleted' => false],
                ];
            }
        };

        $this->assertSame('cus_match', $service->findCustomerIdByEmail('leticia@example.com'));
    }

    public function test_returns_null_when_the_email_has_no_active_customer(): void
    {
        $service = new class extends TeamStripeCustomerService
        {
            protected function stripeCustomersWithEmail(string $email): iterable
            {
                return [
                    (object) ['id' => 'cus_gone', 'email' => 'leticia@example.com', 'deleted' => true],
                ];
            }
        };

        $this->assertNull($service->findCustomerIdByEmail('leticia@example.com'));
        $this->assertNull($service->findCustomerIdByEmail('   '));
    }
}
