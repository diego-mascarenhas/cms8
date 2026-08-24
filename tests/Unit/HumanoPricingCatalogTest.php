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

    public function test_unfiltered_available_plans_still_require_checkout_urls(): void
    {
        $ids = collect(app(AffiliateReferralLinkBuilder::class)->availablePlans())
            ->pluck('id')
            ->all();

        $this->assertContains('assistant', $ids);
        $this->assertContains('hunter', $ids);
        $this->assertNotContains('shop_basic', $ids);
        $this->assertNotContains('mailer_basic', $ids);
    }
}
