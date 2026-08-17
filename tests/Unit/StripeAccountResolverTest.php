<?php

namespace Tests\Unit;

use App\Services\StripeAccountResolver;
use Tests\TestCase;

class StripeAccountResolverTest extends TestCase
{
    public function test_secret_for_any_category_uses_cashier_secret(): void
    {
        config([
            'cashier.secret' => 'sk_env_default',
            'stripe_accounts.mailer.secret' => 'sk_mailer',
            'stripe_accounts.mentoring.secret' => 'sk_mentoring',
        ]);

        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory(''));
        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory('   '));
        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory('mailer'));
        $this->assertSame('sk_env_default', StripeAccountResolver::secretForCategory('mentoring'));
    }

    public function test_has_dedicated_credentials_is_always_false(): void
    {
        config(['stripe_accounts.mailer.secret' => 'sk_mailer']);

        $this->assertFalse(StripeAccountResolver::hasDedicatedCredentials(''));
        $this->assertFalse(StripeAccountResolver::hasDedicatedCredentials('mailer'));
        $this->assertFalse(StripeAccountResolver::hasDedicatedCredentials('mentoring'));
    }

    public function test_key_and_webhook_secret_use_cashier(): void
    {
        config([
            'cashier.key' => 'pk_env_default',
            'cashier.webhook.secret' => 'whsec_env_default',
            'stripe_accounts.mailer.key' => 'pk_mailer',
            'stripe_accounts.mailer.webhook_secret' => 'whsec_mailer',
        ]);

        $this->assertSame('pk_env_default', StripeAccountResolver::keyForCategory('mailer'));
        $this->assertSame('whsec_env_default', StripeAccountResolver::webhookSecretForCategory('mailer'));
    }
}
