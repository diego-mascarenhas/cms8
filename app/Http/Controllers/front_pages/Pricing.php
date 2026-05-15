<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;

class Pricing extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        $plans = collect(config('humano_pricing.plans', []))
            ->map(function (array $plan): array
            {
                $checkoutAvailable = (bool) ($plan['checkout_available'] ?? true);
                $plan['checkout_href'] = $checkoutAvailable
                    ? (string) $plan['checkout_url']
                    : '';

                return $plan;
            })
            ->all();

        return view('content.front-pages.pricing-page', [
            'pageConfigs' => $pageConfigs,
            'plans' => $plans,
        ]);
    }
}
