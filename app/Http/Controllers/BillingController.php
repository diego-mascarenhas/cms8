<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Http\Requests\SendAffiliateInvitationRequest;
use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Services\AffiliateReferralLinkBuilder;
use App\Services\StripeAccountResolver;
use App\Services\TaxIdentifierService;
use App\Services\TeamApiUsageStatsService;
use App\Services\TeamStripeCustomerService;
use App\Support\StripeErrorMessage;
use App\Traits\ConfiguresTeamMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillingController extends Controller
{
    use ConfiguresTeamMail;

    public function index()
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        // Get ALL subscriptions from database (exclude canceled ones)
        $teamSubscriptions = $team->subscriptions()
            ->where('stripe_status', '!=', 'canceled')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('type'); // Group by type: mailer, hosting, domain, licence, etc.

        // Get mailer subscription for email plan info
        $mailerSubscription = $team->subscription('mailer');

        // Get current plan from active subscription or fallback to team setting (e.g. from seeder)
        if ($mailerSubscription && $mailerSubscription->active())
        {
            $currentPlan = EmailPlan::fromStripePriceId($mailerSubscription->stripe_price);
        } else
        {
            $currentPlan = $team->getEmailPlan();
        }

        // Get plan usage configuration
        $planConfig = [
            'monthly_limit' => $currentPlan->getMonthlyLimit(),
            'monthly_used' => (int) $team->getSetting('email_monthly_used', 0),
            'daily_limit' => $currentPlan->getDailyLimit(),
            'daily_used' => (int) $team->getSetting('email_daily_used', 0),
            'contact_limit' => $currentPlan->getContactLimit(),
        ];

        // Get Stripe data
        $stripeData = null;
        $invoices = collect([]);
        $subscriptions = collect([]);
        $paymentMethods = collect([]);

        try
        {
            $customerService = app(TeamStripeCustomerService::class);
            $customerId = $team->stripe_id ?: $customerService->getStripeCustomerIdForCategory($team, 'mailer');
            if ($customerId)
            {
                \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

                $customer = \Stripe\Customer::retrieve($customerId);

                // Verify the customer belongs to this team
                // Check metadata team_id if it exists
                $customerTeamId = $customer->metadata->team_id ?? null;
                if ($customerTeamId && (int) $customerTeamId !== (int) $team->id)
                {
                    \Log::warning('Customer stripe_id does not match team in BillingController', [
                        'team_id' => $team->id,
                        'customer_team_id' => $customerTeamId,
                        'stripe_customer_id' => $customerId,
                    ]);
                    // Don't use this customer's data - team has wrong stripe_id
                    $stripeData = null;
                } else
                {
                    // Get tax IDs separately for more reliability
                    try
                    {
                        $taxIds = \Stripe\Customer::allTaxIds($customerId, ['limit' => 10]);
                        $customer->tax_ids = $taxIds;
                    } catch (\Exception $e)
                    {
                        \Log::warning('Could not retrieve tax IDs: '.$e->getMessage());
                    }

                    // Get invoices
                    $invoicesData = \Stripe\Invoice::all([
                        'customer' => $customerId,
                        'limit' => 20,
                    ]);
                    $invoices = collect($invoicesData->data);

                    // Get subscriptions
                    $subscriptionsData = \Stripe\Subscription::all([
                        'customer' => $customerId,
                        'limit' => 10,
                    ]);
                    $subscriptions = collect($subscriptionsData->data);

                    // Get payment methods
                    $paymentMethodsData = \Stripe\PaymentMethod::all([
                        'customer' => $customerId,
                        'type' => 'card',
                    ]);
                    $paymentMethods = collect($paymentMethodsData->data);

                    $stripeData = [
                        'customer' => $customer,
                        'invoices' => $invoicesData,
                        'subscriptions' => $subscriptionsData,
                    ];
                }
            } else
            {
                // Team has no stripe_id - don't show any billing data
                // This is correct behavior: new teams should not see data from other teams
                \Log::info('Team has no stripe_id, not showing billing data', [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                ]);
                $stripeData = null;
                $invoices = collect([]);
                $subscriptions = collect([]);
                $paymentMethods = collect([]);
            }
        } catch (\Exception $e)
        {
            Log::error('Error fetching Stripe data: '.$e->getMessage(), [
                'team_id' => $team->id,
                'stripe_id' => $team->stripe_id,
            ]);
            $stripeData = null;
        }

        $team->resetProspectMonthlyLimitsIfNeeded();
        $remainingProspectCredits = $team->getRemainingProspectCredits();
        $currentProspectPlan = $team->getProspectPlan();

        $affiliateCommissionsAsReferrer = collect();
        $affiliateCommissionsAsPayer = collect();
        $affiliateTotalsAsReferrer = [];
        $affiliateTotalsAsPayer = [];
        $affiliateCommissionPercent = 0.0;
        $affiliateReferralCode = null;
        $affiliateReferralPlans = [];
        $affiliateInvitations = collect();

        if ($team->hasModule('affiliates'))
        {
            $linkBuilder = app(AffiliateReferralLinkBuilder::class);

            $affiliateCommissionsAsReferrer = $team->billingAffiliateCommissionsAsReferrer()
                ->with(['payingTeam'])
                ->latest()
                ->limit(200)
                ->get();

            $affiliateCommissionsAsPayer = $team->billingAffiliateCommissionsAsPayer()
                ->with(['referrerTeam'])
                ->latest()
                ->limit(200)
                ->get();

            $affiliateTotalsAsReferrer = $this->sumAffiliateCommissionsByCurrency($affiliateCommissionsAsReferrer);
            $affiliateTotalsAsPayer = $this->sumAffiliateCommissionsByCurrency($affiliateCommissionsAsPayer);
            $affiliateCommissionPercent = (float) config('humano_pricing.affiliate_commission_percent', 0);
            $affiliateReferralCode = $linkBuilder->referralCode($team);

            foreach ($linkBuilder->availablePlans() as $plan)
            {
                $affiliateReferralPlans[] = array_merge($plan, [
                    'referral_url' => $affiliateReferralCode !== null
                        ? $linkBuilder->buildLink($plan['checkout_url'], $affiliateReferralCode)
                        : null,
                ]);
            }

            $affiliateInvitations = $team->affiliateInvitations()
                ->with('invitedBy')
                ->latest()
                ->limit(100)
                ->get();
        }

        $tokenStats = null;
        if ($user->hasRole(['root', 'admin']))
        {
            $tokenStats = TeamApiUsageStatsService::forTeam((int) $team->id);
        }

        return view('billing.index', compact(
            'team',
            'currentPlan',
            'mailerSubscription',
            'teamSubscriptions',
            'planConfig',
            'invoices',
            'subscriptions',
            'paymentMethods',
            'stripeData',
            'remainingProspectCredits',
            'currentProspectPlan',
            'affiliateCommissionsAsReferrer',
            'affiliateCommissionsAsPayer',
            'affiliateTotalsAsReferrer',
            'affiliateTotalsAsPayer',
            'affiliateCommissionPercent',
            'affiliateReferralCode',
            'affiliateReferralPlans',
            'affiliateInvitations',
            'tokenStats',
        ));
    }

    public function sendAffiliateInvite(SendAffiliateInvitationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $linkBuilder = app(AffiliateReferralLinkBuilder::class);
        $referralCode = $linkBuilder->referralCode($team);

        if ($referralCode === null)
        {
            return redirect()->route('billing.index')
                ->with('error', __('Your team does not have a billing reference code yet. Complete a subscription first.'));
        }

        $planId = (string) $request->validated('invite_plan');
        $plan = collect($linkBuilder->availablePlans())->firstWhere('id', $planId);

        if ($plan === null)
        {
            return redirect()->route('billing.index')
                ->with('error', __('The selected plan is not available.'));
        }

        $checkoutUrl = $linkBuilder->buildLink(
            $plan['checkout_url'],
            $referralCode,
            (string) $request->validated('invite_email'),
        );

        $planMarketing = $linkBuilder->planMarketing($planId) ?? [
            'name' => $plan['name'],
            'description' => '',
            'features' => [],
            'image_url' => $linkBuilder->planImageUrl($planId),
        ];

        $this->configureMailForTeam($team);

        Mail::to((string) $request->validated('invite_email'))->send(
            new AffiliatePurchaseInvitationMail(
                $team,
                $user,
                (string) $request->validated('invite_name'),
                $planMarketing['name'],
                $planMarketing['description'],
                $planMarketing['features'],
                $planMarketing['image_url'],
                $checkoutUrl,
                $linkBuilder->pricingPageUrl(),
            ),
        );

        AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => (string) $request->validated('invite_name'),
            'invitee_email' => (string) $request->validated('invite_email'),
            'plan_id' => $planId,
            'plan_name' => $planMarketing['name'],
        ]);

        return redirect()->route('billing.index')
            ->with('success', __('Invitación enviada correctamente.'));
    }

    public function update(Request $request)
    {
        // Validation rules with smart tax_id validation
        $request->validate([
            'individual_name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'country' => 'required|string|max:2',
            'phone' => 'required|string|max:50',
            'tax_id' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($request)
                {
                    $taxIdentifierService = app(TaxIdentifierService::class);
                    $taxId = $taxIdentifierService->normalize($value);
                    if ($taxId === '' || ! $taxIdentifierService->isValidForCountry($request->country, $taxId))
                    {
                        $fail('El formato de la Identificación Fiscal no es válido para el país seleccionado.');
                    }
                },
            ],
        ]);

        $user = auth()->user();
        $team = $user->currentTeam;

        $taxIdentifierService = app(TaxIdentifierService::class);
        $taxIdNormalized = $taxIdentifierService->normalize($request->tax_id);

        try
        {
            $customerService = app(TeamStripeCustomerService::class);
            $billingCustomerId = $customerService->getOrCreateStripeCustomerIdForCategory($team, 'mailer');
            if (! $billingCustomerId)
            {
                return redirect()->route('billing.index')
                    ->with('error', __('No se pudo crear el cliente de facturación.'));
            }

            \Stripe\Stripe::setApiKey(StripeAccountResolver::secretForCategory('mailer'));

            $customerName = $request->business_name ?: $request->individual_name;
            $taxIdError = null;
            $phone = $this->normalizePhoneNumber($request->phone, $request->country);

            // Step 1: Update customer basic info
            \Stripe\Customer::update($billingCustomerId, [
                'name' => $customerName,
                'phone' => $phone,
                'address' => [
                    'country' => $request->country,
                ],
            ]);

            // Step 2: Update or create tax ID
            try
            {
                $taxIds = \Stripe\Customer::allTaxIds($billingCustomerId, ['limit' => 100]);
                foreach ($taxIds->data as $taxId)
                {
                    \Stripe\Customer::deleteTaxId($billingCustomerId, $taxId->id);
                }

                $taxIdType = $taxIdentifierService->resolveStripeTaxIdType($request->country, $taxIdNormalized);

                if ($taxIdType !== null)
                {
                    \Stripe\Customer::createTaxId($billingCustomerId, [
                        'type' => $taxIdType,
                        'value' => $taxIdNormalized,
                    ]);
                    \Log::info('Tax ID created successfully for customer: '.$billingCustomerId);
                }
            } catch (\Exception $e)
            {
                \Log::error('Could not update tax ID', array_merge([
                    'customer' => $billingCustomerId,
                    'country' => $request->country,
                    'tax_id' => $taxIdNormalized,
                ], StripeErrorMessage::logContext($e)));
                $taxIdError = StripeErrorMessage::display($e);
            }

            \Stripe\Customer::update($billingCustomerId, [
                'metadata' => [
                    'individual_name' => $request->individual_name,
                    'business_name' => $request->business_name,
                    'tax_id' => $taxIdNormalized,
                    'country' => $request->country,
                ],
            ]);

            $successMessage = 'Datos de facturación actualizados correctamente.';
            if ($taxIdError)
            {
                $successMessage .= ' Sin embargo, hubo un problema al actualizar el ID Fiscal: '.$taxIdError;

                return redirect()->route('billing.index')->with('warning', $successMessage);
            }

            return redirect()->route('billing.index')->with('success', $successMessage);
        } catch (\Exception $e)
        {
            \Log::error('Error updating billing data', StripeErrorMessage::logContext($e));

            return redirect()->back()->withInput()->with('error', 'Error al actualizar los datos de facturación: '.StripeErrorMessage::display($e));
        }
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phone, string $country): string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // If already has + at the start, return as is (already in international format)
        if (str_starts_with($cleaned, '+'))
        {
            return $cleaned;
        }

        // Get country code based on country
        $countryCode = match ($country)
        {
            'AR' => '+54',
            'ES' => '+34',
            'MX' => '+52',
            'US' => '+1',
            'CO' => '+57',
            'CL' => '+56',
            'PE' => '+51',
            'UY' => '+598',
            'BR' => '+55',
            default => '',
        };

        // For Argentina, ensure mobile numbers have the '9' prefix after country code
        if ($country === 'AR' && ! str_starts_with($cleaned, '9'))
        {
            // If starts with 15, remove it (old mobile format)
            if (str_starts_with($cleaned, '15'))
            {
                $cleaned = substr($cleaned, 2);
            }
            // Add 9 prefix for mobile
            $cleaned = '9'.$cleaned;
        }

        return $countryCode.$cleaned;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BillingAffiliateCommission>  $rows
     * @return array<string, array{paid_cents: int, commission_cents: int}>
     */
    private function sumAffiliateCommissionsByCurrency($rows): array
    {
        $totals = [];

        foreach ($rows as $row)
        {
            $currency = strtoupper((string) $row->currency);
            if (! isset($totals[$currency]))
            {
                $totals[$currency] = ['paid_cents' => 0, 'commission_cents' => 0];
            }
            $totals[$currency]['paid_cents'] += (int) $row->amount_paid_cents;
            $totals[$currency]['commission_cents'] += (int) $row->commission_amount_cents;
        }

        return $totals;
    }
}
