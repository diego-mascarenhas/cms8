<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;
use App\Services\HumanoPricingPlanResolver;

class Landing extends Controller
{
    public function __construct(
        private readonly HumanoPricingPlanResolver $pricingPlanResolver,
    ) {}

    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        return view('content.front-pages.humano-landing-page', [
            'pageConfigs' => $pageConfigs,
            'plans' => $this->pricingPlanResolver->plansForDisplay(),
        ]);
    }
}
