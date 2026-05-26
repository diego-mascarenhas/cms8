<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;
use App\Services\HumanoPricingPlanResolver;

class Pricing extends Controller
{
    public function __construct(
        private readonly HumanoPricingPlanResolver $pricingPlanResolver,
    ) {}

    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        $plans = $this->pricingPlanResolver->plansForDisplay();

        return view('content.front-pages.pricing-page', [
            'pageConfigs' => $pageConfigs,
            'plans' => $plans,
        ]);
    }
}
