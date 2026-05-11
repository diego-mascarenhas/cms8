<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;

class Pricing extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];
        $couponCode = trim((string) config('humano_pricing.coupon_code', ''));

        $plans = collect(config('humano_pricing.plans', []))
            ->map(function (array $plan) use ($couponCode): array
            {
                $plan['checkout_href'] = $this->checkoutHref((string) $plan['checkout_url'], $couponCode);

                return $plan;
            })
            ->all();

        return view('content.front-pages.pricing-page', [
            'pageConfigs' => $pageConfigs,
            'plans' => $plans,
        ]);
    }

    private function checkoutHref(string $baseUrl, string $couponCode): string
    {
        if ($couponCode === '')
        {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'prefilled_promo_code='.rawurlencode($couponCode);
    }
}
