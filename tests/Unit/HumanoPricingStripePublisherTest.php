<?php

namespace Tests\Unit;

use App\Services\Billing\HumanoPricingStripePublisher;
use Stripe\StripeClient;
use Tests\TestCase;

class HumanoPricingStripePublisherTest extends TestCase
{
    public function test_publishable_plans_are_paid_assistant_shop_ads_and_projects(): void
    {
        $publisher = new HumanoPricingStripePublisher(new StripeClient('sk_test_dummy'));
        $ids = collect($publisher->publishablePlans())->pluck('id')->all();

        $this->assertSame(
            ['assistant', 'shop_basic', 'shop_premium', 'shop_profesional', 'ads', 'projects'],
            $ids,
        );
        $this->assertSame('19', collect($publisher->publishablePlans())->firstWhere('id', 'shop_basic')['monthly_amount']);
        $this->assertSame('49', collect($publisher->publishablePlans())->firstWhere('id', 'ads')['monthly_amount']);
    }

    public function test_publishable_plans_skip_affiliates_and_platform(): void
    {
        $ids = collect((new HumanoPricingStripePublisher(new StripeClient('sk_test_dummy')))->publishablePlans())
            ->pluck('id')
            ->all();

        $this->assertNotContains('affiliates', $ids);
        $this->assertNotContains('estimator', $ids);
        $this->assertContains('assistant', $ids);
        $this->assertNotContains('mailer_basic', $ids);
        $this->assertNotContains('hunter', $ids);
    }

    public function test_estimator_plan_is_free_and_not_publishable(): void
    {
        $plan = collect(config('humano_pricing.plans'))->firstWhere('id', 'estimator');

        $this->assertIsArray($plan);
        $this->assertSame('0', $plan['monthly_amount'] ?? null);
        $this->assertSame('0', $plan['yearly_amount'] ?? null);
        $this->assertSame('', $plan['stripe_price_monthly_id'] ?? null);
        $this->assertTrue($plan['checkout_available'] ?? false);
        $this->assertSame('Estimator', trans('humano_pricing.plans.estimator.name', [], 'en'));
    }

    public function test_shop_and_ads_plans_have_test_stripe_ids_in_config(): void
    {
        $shop = collect(config('humano_pricing.plans'))->firstWhere('id', 'shop_basic');
        $ads = collect(config('humano_pricing.plans'))->firstWhere('id', 'ads');
        $assistant = collect(config('humano_pricing.plans'))->firstWhere('id', 'assistant');

        $this->assertSame('prod_V89Ch5pNA8GG1s', $shop['stripe_product_id'] ?? null);
        $this->assertSame('price_1U7suBRwN51ygFde4qEpYyXf', $shop['stripe_price_monthly_id'] ?? null);
        $this->assertSame('19', $shop['monthly_amount'] ?? null);
        $this->assertSame('prod_V89CoEJptr2nT5', $ads['stripe_product_id'] ?? null);
        $this->assertSame('49', $ads['monthly_amount'] ?? null);
        $this->assertSame('prod_V8Jgzp5AQyRYmC', $assistant['stripe_product_id'] ?? null);
        $this->assertSame('price_1U832kRwN51ygFdeVvMTtNJH', $assistant['stripe_price_monthly_id'] ?? null);
        $this->assertSame('price_1U832kRwN51ygFdebJxgLunP', $assistant['stripe_price_yearly_id'] ?? null);
        $this->assertSame('49', $assistant['monthly_amount'] ?? null);
    }

    public function test_shop_plan_names_are_audience_tiers(): void
    {
        $this->assertSame('Shop Freelancer', trans('humano_pricing.plans.shop_basic.name', [], 'en'));
        $this->assertSame('Shop Commerce', trans('humano_pricing.plans.shop_premium.name', [], 'en'));
        $this->assertSame('Shop Enterprise', trans('humano_pricing.plans.shop_profesional.name', [], 'en'));
        $this->assertSame('Shop Freelancer', trans('humano_pricing.plans.shop_basic.name', [], 'es_ES'));
        $this->assertSame('Shop Commerce', trans('humano_pricing.plans.shop_premium.name', [], 'es_ES'));
        $this->assertSame('Shop Enterprise', trans('humano_pricing.plans.shop_profesional.name', [], 'es_ES'));
    }
}
