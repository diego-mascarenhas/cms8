<?php

namespace Tests\Unit;

use App\Services\StripeAccountResolver;
use Tests\TestCase;

class StripeAccountResolverTest extends TestCase
{
    public function test_secret_for_empty_category_uses_cashier_secret(): void
    {
        config(['cashier.secret' => 'sk_env_default']);

        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory(''));
        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory('   '));
    }

    public function test_has_dedicated_credentials_false_for_empty_category(): void
    {
        config(['stripe_accounts.mailer.secret' => 'sk_mailer']);

        $this->assertFalse(StripeAccountResolver::hasDedicatedCredentials(''));
    }

    public function test_has_dedicated_credentials_true_when_secret_set(): void
    {
        config(['stripe_accounts.mailer.secret' => 'sk_mailer']);

        $this->assertTrue(StripeAccountResolver::hasDedicatedCredentials('mailer'));
    }

    public function test_secret_for_category_without_dedicated_config_falls_back_to_cashier(): void
    {
        config([
            'stripe_accounts.mailer.secret' => null,
            'cashier.secret' => 'sk_default',
        ]);

        $this->assertSame('sk_default', StripeAccountResolver::secretForCategory('mailer'));
    }
}
