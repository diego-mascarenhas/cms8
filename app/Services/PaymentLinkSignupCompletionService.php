<?php

namespace App\Services;

use App\Contracts\CheckoutSessionRetriever;
use App\Models\Team;
use App\Models\User;
use App\Services\Stripe\StripeCheckoutSessionLogFormatter;
use App\Support\PaymentLinkSignupOutcome;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;

class PaymentLinkSignupCompletionService
{
    /**
     * Humano public plan Payment Links always use the platform Stripe account (Cashier / .env), not per-team Stripe from settings.
     */
    private const PLATFORM_STRIPE_CATEGORY = '';

    public function __construct(
        private readonly CheckoutSessionRetriever $checkoutSessionRetriever,
        private readonly TeamStripeCustomerService $teamStripeCustomerService,
        private readonly TeamCheckoutSessionSubscriptionSyncer $teamCheckoutSessionSubscriptionSyncer,
        private readonly PaymentLinkAffiliateTeamAttributionService $paymentLinkAffiliateTeamAttributionService,
    ) {}

    public function complete(string $sessionId): PaymentLinkSignupOutcome
    {
        Log::info('Payment link signup: complete() started', [
            'session_id_prefix' => substr($sessionId, 0, 16),
            'stripe_scope' => 'humano_platform',
        ]);

        $mode = strtolower((string) config('humano_pricing.signup_completion', 'payment_link'));
        if ($mode === 'register_first')
        {
            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('register')
                    ->with('info', __('humano_pricing.checkout_complete_register_first')),
            );
        }

        $category = self::PLATFORM_STRIPE_CATEGORY;

        $session = $this->checkoutSessionRetriever->retrieve($sessionId, $category);
        if (! $session instanceof Session)
        {
            Log::warning('Payment link signup: Stripe checkout session could not be loaded', [
                'stripe_scope' => 'humano_platform',
                'session_id_prefix' => substr($sessionId, 0, 16),
            ]);

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_invalid_session')),
            );
        }

        if ($session->status !== 'complete')
        {
            Log::warning('Payment link signup: Stripe session not complete', array_merge(
                ['stripe_scope' => 'humano_platform', 'reason' => 'session_status'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_not_paid')),
            );
        }

        if ($session->payment_status !== 'paid')
        {
            Log::warning('Payment link signup: Stripe session payment not paid', array_merge(
                ['stripe_scope' => 'humano_platform', 'reason' => 'payment_status'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_not_paid')),
            );
        }

        if (! in_array($session->mode, ['subscription', 'payment'], true))
        {
            Log::warning('Payment link signup: unsupported Stripe checkout mode', array_merge(
                ['stripe_scope' => 'humano_platform', 'reason' => 'unsupported_mode'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_unsupported_mode')),
            );
        }

        $email = $this->resolvePayerEmail($session);
        if ($email === null || $email === '')
        {
            Log::warning('Payment link signup: no payer email on Stripe session', array_merge(
                ['stripe_scope' => 'humano_platform', 'reason' => 'no_email'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_no_email')),
            );
        }

        $sessionCustomerId = $this->extractSessionCustomerId($session);
        if ($sessionCustomerId === null || $sessionCustomerId === '')
        {
            Log::warning('Payment link signup: no Stripe customer on session', array_merge(
                ['stripe_scope' => 'humano_platform', 'reason' => 'no_customer'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_no_customer')),
            );
        }

        $isNewUser = false;
        $user = User::where('email', $email)->first();
        if (! $user)
        {
            $isNewUser = true;
            $user = $this->createUserWithPersonalTeam($email, $session);
        } else
        {
            $user->refresh();
            if (! $this->resolveTeamForUser($user))
            {
                $this->ensurePersonalTeamForReturningUser($user, $session);
                $user->refresh();
            }
        }

        $team = $this->resolveTeamForUser($user);
        if (! $team)
        {
            Log::error('Payment link signup: user has no team after signup flow', array_merge(
                ['stripe_scope' => 'humano_platform', 'user_id' => $user->id, 'reason' => 'no_team'],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_no_team')),
            );
        }

        $teamCustomerId = $this->teamStripeCustomerService->getStripeCustomerIdForCategory($team, $category);
        if (($teamCustomerId ?? '') === '' && $sessionCustomerId !== '')
        {
            $this->teamStripeCustomerService->persistStripeCustomerIdForCategory($team, $category, $sessionCustomerId);
            $teamCustomerId = $this->teamStripeCustomerService->getStripeCustomerIdForCategory($team, $category);
        }

        if (($sessionCustomerId ?? '') !== ($teamCustomerId ?? ''))
        {
            Log::warning('Payment link signup: Stripe customer id mismatch vs team', array_merge(
                [
                    'stripe_scope' => 'humano_platform',
                    'reason' => 'customer_mismatch',
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'session_customer' => $sessionCustomerId,
                    'team_customer' => $teamCustomerId,
                ],
                StripeCheckoutSessionLogFormatter::toLogContext($session),
            ));

            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('login')
                    ->with('error', __('humano_pricing.checkout_complete_customer_mismatch')),
            );
        }

        $team->refresh();
        $this->teamCheckoutSessionSubscriptionSyncer->sync($team, $session, $category, (int) $user->id, true);

        $this->paymentLinkAffiliateTeamAttributionService->syncTeamReferrerFromSession(
            $team->fresh(),
            $session,
        );

        Log::info('Payment link signup: Stripe checkout applied to Humano user', array_merge(
            [
                'stripe_scope' => 'humano_platform',
                'user_id' => $user->id,
                'team_id' => $team->id,
                'is_new_user' => $isNewUser,
                'resolved_email' => $email,
            ],
            StripeCheckoutSessionLogFormatter::toLogContext($session),
        ));

        return PaymentLinkSignupOutcome::login($user, $isNewUser);
    }

    private function resolvePayerEmail(Session $session): ?string
    {
        $details = $session->customer_details ?? null;
        if ($details && ! empty($details->email) && is_string($details->email))
        {
            return strtolower(trim($details->email));
        }

        if (! empty($session->customer_email) && is_string($session->customer_email))
        {
            return strtolower(trim($session->customer_email));
        }

        $customerRef = $session->customer ?? null;
        if ($customerRef)
        {
            try
            {
                $customer = is_string($customerRef)
                    ? \Stripe\Customer::retrieve($customerRef)
                    : $customerRef;
                if ($customer && ! empty($customer->email) && is_string($customer->email))
                {
                    return strtolower(trim($customer->email));
                }
            } catch (\Exception $e)
            {
                Log::warning('Payment link signup: could not load Stripe customer for email', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function extractSessionCustomerId(Session $session): ?string
    {
        $customer = $session->customer ?? null;
        if (is_string($customer) && $customer !== '')
        {
            return $customer;
        }
        if (is_object($customer) && isset($customer->id))
        {
            return (string) $customer->id;
        }

        return null;
    }

    private function resolveTeamForUser(User $user): ?Team
    {
        $user->loadMissing(['ownedTeams']);
        $current = $user->currentTeam;
        if ($current)
        {
            return $current;
        }

        $personal = $user->ownedTeams()->where('personal_team', true)->orderBy('id')->first();

        return $personal ?? $user->ownedTeams()->orderBy('id')->first();
    }

    /**
     * Prefer the payer's person name for Humano (users.name), not the company / legal name from Stripe.
     * Stripe Checkout exposes customer_details.individual_name and business_name; customer_details.name
     * often mirrors the business name for B2B checkouts.
     */
    private function resolvePayerDisplayNameForUser(string $email, Session $session): string
    {
        $details = $session->customer_details ?? null;
        if ($details === null)
        {
            return $this->displayNameFromEmailLocalPart($email);
        }

        $individualName = $this->stripeCustomerDetailString($details, 'individual_name');
        if ($individualName !== '')
        {
            return $individualName;
        }

        $businessName = $this->stripeCustomerDetailString($details, 'business_name');
        $genericName = $this->stripeCustomerDetailString($details, 'name');

        if ($genericName !== '' && ($businessName === '' || strcasecmp($genericName, $businessName) !== 0))
        {
            return $genericName;
        }

        return $this->displayNameFromEmailLocalPart($email);
    }

    /**
     * @param  \Stripe\StripeObject|object  $details
     */
    private function stripeCustomerDetailString(object $details, string $key): string
    {
        if (! isset($details->{$key}))
        {
            return '';
        }

        $value = $details->{$key};
        if (! is_string($value))
        {
            return '';
        }

        return trim($value);
    }

    private function displayNameFromEmailLocalPart(string $email): string
    {
        $local = Str::before($email, '@');

        return Str::title(str_replace(['.', '_', '-'], ' ', $local));
    }

    private function createUserWithPersonalTeam(string $email, Session $session): User
    {
        $name = $this->resolvePayerDisplayNameForUser($email, $session);

        $user = DB::transaction(function () use ($name, $email)
        {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
                'email_verified_at' => now(),
            ]);

            $team = $user->ownedTeams()->save(Team::forceCreate([
                'user_id' => $user->id,
                'name' => explode(' ', $user->name, 2)[0]."'s Team",
                'personal_team' => true,
            ]));

            $user->forceFill([
                'current_team_id' => $team->id,
            ])->save();

            return $user->fresh();
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * Existing Humano user (same email as Stripe payer) may have no team row; create a personal workspace
     * so subscription and billing can attach like a new registration.
     */
    private function ensurePersonalTeamForReturningUser(User $user, Session $session): void
    {
        DB::transaction(function () use ($user, $session): void
        {
            $displayName = trim((string) ($user->name ?? ''));
            if ($displayName === '')
            {
                $displayName = $this->resolvePayerDisplayNameForUser((string) $user->email, $session);
            }

            $team = $user->ownedTeams()->save(Team::forceCreate([
                'user_id' => $user->id,
                'name' => explode(' ', $displayName, 2)[0]."'s Team",
                'personal_team' => true,
            ]));

            $user->forceFill([
                'current_team_id' => $team->id,
            ])->save();
        });
    }
}
