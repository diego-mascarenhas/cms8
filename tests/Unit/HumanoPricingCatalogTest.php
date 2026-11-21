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
        $this->assertSame('hosting', HumanoPricingCatalog::normalize('Hosting'));
        $this->assertSame('vps', HumanoPricingCatalog::normalize('vps'));
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
        $this->assertContains('hosting_cloud_starter', $ids);
        $this->assertContains('hosting_vps_value', $ids);
        $this->assertContains('hosting_wordpress', $ids);
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

    public function test_referral_checkout_uses_the_requesting_app(): void
    {
        $url = app(AffiliateReferralLinkBuilder::class)->referralUrl([
            'id' => 'estimator',
            'checkout_url' => '',
        ], 'cus_referrer', null, 'https://estimator.idoneo.dev/affiliates');

        $this->assertSame('https://estimator.idoneo.dev/pricing?ref=cus_referrer', $url);
        $this->assertNull(app(AffiliateReferralLinkBuilder::class)->shareOrigin('https://evil.example'));
    }

    public function test_revision_alpha_hosting_plans_use_landing_referral_urls(): void
    {
        $cloud = collect(app(AffiliateReferralLinkBuilder::class)->availablePlans('hosting'))
            ->firstWhere('id', 'hosting_cloud_starter');
        $vps = collect(app(AffiliateReferralLinkBuilder::class)->availablePlans('vps'))
            ->firstWhere('id', 'hosting_vps_value');

        $this->assertNotNull($cloud);
        $this->assertNotNull($vps);
        $this->assertSame('https://revisionalpha.com/cloud', $cloud['checkout_url']);
        $this->assertSame('https://revisionalpha.com/servidores-dedicados', $vps['checkout_url']);

        $url = app(AffiliateReferralLinkBuilder::class)->referralUrl($cloud, 'cus_referrer');
        $this->assertStringContainsString('/affiliate/capture', $url);
        $this->assertStringContainsString('ref=cus_referrer', $url);
        $this->assertStringContainsString(rawurlencode('https://revisionalpha.com/cloud'), $url);
    }
}
