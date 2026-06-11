<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;
use App\Services\AffiliateReferralAttributionService;
use App\Services\HumanoPricingPlanResolver;
use Illuminate\Http\Request;

class Pricing extends Controller
{
    public function __construct(
        private readonly HumanoPricingPlanResolver $pricingPlanResolver,
        private readonly AffiliateReferralAttributionService $affiliateReferralAttribution,
    ) {}

    public function index(Request $request)
    {
        $pageConfigs = ['myLayout' => 'front'];

        $referrerFromQuery = trim((string) $request->query('ref', ''));
        if ($referrerFromQuery !== '')
        {
            $this->affiliateReferralAttribution->captureIfValid($request, $referrerFromQuery);
        }

        $storedReferrer = $this->affiliateReferralAttribution->getStoredReferrerStripeId($request);
        $plans = $this->pricingPlanResolver->plansWithCheckoutAvailable($storedReferrer);

        return view('content.front-pages.pricing-page', [
            'pageConfigs' => $pageConfigs,
            'plans' => $plans,
        ]);
    }
}
