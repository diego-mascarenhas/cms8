<?php

namespace Tests\Unit;

use App\Services\AffiliateReferralLinkBuilder;
use App\Support\HumanoPricingCatalog;
use Tests\TestCase;

class HumanoPricingCatalogTest extends TestCase
{
    public function test_normalize_accepts_product_catalogs(): void
    {
        $this->assertSame('shop', HumanoPricingCatalog::normalize('Shop'));
        $this->assertSame('ads', HumanoPricingCatalog::normalize('ads'));
        $this->assertSame('mailer', HumanoPricingCatalog::normalize('mailer'));
        $this->assertSame('estimator', HumanoPricingCatalog::normalize('Estimator'));
        $this->assertNull(HumanoPricingCatalog::normalize('unknown'));
        $this->assertNull(HumanoPricingCatalog::normalize(null));
    }

    public function test_available_plans_for_shop_do_not_include_platform_products(): void
    {
        $plans = app(AffiliateReferralLinkBuilder::class)->availablePlans('shop');

        $this->assertSame(
            ['shop_basic', 'shop_premium', 'shop_profesional'],
            collect($plans)->pluck('id')->all(),
        );
        $this->assertNotContains('hunter', collect($plans)->pluck('id')->all());
        $this->assertNotContains('assistant', collect($plans)->pluck('id')->all());
    }

    public function test_unfiltered_available_plans_include_every_product_in_usd(): void
    {
        $plans = app(AffiliateReferralLinkBuilder::class)->availablePlans();
        $ids = collect($plans)->pluck('id')->all();

        $this->assertContains('assistant', $ids);
        $this->assertContains('hunter', $ids);
        $this->assertContains('shop_basic', $ids);
        $this->assertContains('mailer_basic', $ids);
        $this->assertContains('ads', $ids);
        $this->assertContains('projects', $ids);
        $this->assertContains('estimator', $ids);
        $this->assertContains('fanyion', $ids);
        $this->assertNotContains('innovation', $ids);

        foreach ($plans as $plan)
        {
            $this->assertSame('usd', $plan['currency']);
        }

        $assistant = collect($plans)->firstWhere('id', 'assistant');
        $this->assertSame('54', $assistant['monthly_amount']);
    }

    public function test_plans_without_a_payment_link_get_a_referral_checkout_url(): void
    {
        $url = app(AffiliateReferralLinkBuilder::class)->referralUrl([
            'id' => 'mailer_basic',
            'checkout_url' => '',
        ], 'cus_referrer');

        $this->assertStringContainsString('/affiliate/checkout/mailer_basic', $url);
        $this->assertStringContainsString('ref=cus_referrer', $url);
    }
}
