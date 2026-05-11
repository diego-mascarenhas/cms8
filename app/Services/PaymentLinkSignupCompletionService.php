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
    public function __construct(
        private readonly CheckoutSessionRetriever $checkoutSessionRetriever,
        private readonly TeamStripeCustomerService $teamStripeCustomerService,
        private readonly TeamCheckoutSessionSubscriptionSyncer $teamCheckoutSessionSubscriptionSyncer,
    ) {}

    public function complete(string $sessionId, string $category): PaymentLinkSignupOutcome
    {
        $mode = strtolower((string) config('humano_pricing.signup_completion', 'payment_link'));
        if ($mode === 'register_first')
        {
            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('register')
                    ->with('info', __('humano_pricing.checkout_complete_register_first')),
            );
        }

        $category = StripeAccountResolver::normalizeCategory($category);

        $session = $this->checkoutSessionRetriever->retrieve($sessionId, $category);
        if (! $session instanceof Session)
        {
            return PaymentLinkSignupOutcome::redirectTo(
                redirect()->route('pricing')
                    ->with('error', __('humano_pricing.checkout_complete_invalid_session')),
            );
        }

        if ($session->status !== 'complete')
        {
            Log::warning('Payment link signup: Stripe session not complete', array_merge(
                ['category' => $category, 'reason' => 'session_status'],
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
                ['category' => $category, 'reason' => 'payment_status'],
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
                ['category' => $category, 'reason' => 'unsupported_mode'],
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
                ['category' => $category, 'reason' => 'no_email'],
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
                ['category' => $category, 'reason' => 'no_customer'],
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
                ['category' => $category, 'user_id' => $user->id, 'reason' => 'no_team'],
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
                    'category' => $category,
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

        Log::info('Payment link signup: Stripe checkout applied to Humano user', array_merge(
            [
                'category' => $category,
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

    private function createUserWithPersonalTeam(string $email, Session $session): User
    {
        $details = $session->customer_details ?? null;
        $name = trim((string) ($details->name ?? ''));
        if ($name === '')
        {
            $local = Str::before($email, '@');
            $name = Str::title(str_replace(['.', '_', '-'], ' ', $local));
        }

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
            $details = $session->customer_details ?? null;
            $displayName = trim((string) ($user->name ?? ''));
            if ($displayName === '')
            {
                $displayName = trim((string) ($details->name ?? ''));
            }
            if ($displayName === '')
            {
                $local = Str::before((string) $user->email, '@');
                $displayName = Str::title(str_replace(['.', '_', '-'], ' ', $local));
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
