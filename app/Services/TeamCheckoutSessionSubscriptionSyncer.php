<?php

namespace App\Services;

use App\Enums\EmailPlan;
use App\Enums\ProspectPlan;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class TeamCheckoutSessionSubscriptionSyncer
{
    /**
     * Persist subscription rows and entitlements from a completed Stripe Checkout session.
     *
     * @param  bool  $fromPublicPaymentLinkCheckout  When true, marks the subscription so registration billing gate accepts public /pricing Payment Link checkouts.
     */
    public function sync(Team $team, Session $session, string $category, int $actingUserId, bool $fromPublicPaymentLinkCheckout = false): void
    {
        Stripe::setApiKey(StripeAccountResolver::secretForCategory($category));

        $subscriptionId = $session->subscription;
        if ($subscriptionId)
        {
            $subscriptionStripeId = is_string($subscriptionId) ? $subscriptionId : (string) $subscriptionId->id;
            $stripeSubscription = \Stripe\Subscription::retrieve([
                'id' => $subscriptionStripeId,
                'expand' => ['items.data.price.product'],
            ]);

            $priceId = $stripeSubscription->items->data[0]->price->id;
            $rawProduct = $stripeSubscription->items->data[0]->price->product;
            $productId = is_string($rawProduct) ? $rawProduct : (string) $rawProduct->id;
            $subscriptionMetadata = $stripeSubscription->metadata ? $stripeSubscription->metadata->toArray() : [];
            Log::info('Stripe Subscription retrieved from checkout session', [
                'team_id' => $team->id,
                'checkout_session_id' => $session->id ?? null,
                'stripe_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'livemode' => $stripeSubscription->livemode ?? null,
                'current_period_start' => $stripeSubscription->current_period_start ?? null,
                'current_period_end' => $stripeSubscription->current_period_end ?? null,
                'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? null,
                'stripe_price_id' => $priceId,
                'stripe_product_id' => $productId,
                'quantity' => $stripeSubscription->items->data[0]->quantity ?? null,
                'metadata' => $subscriptionMetadata,
            ]);

            $subscriptionProduct = SubscriptionProduct::where('stripe_price', $priceId)
                ->orWhere('stripe_product', $productId)
                ->orWhere('stripe_id', $productId)
                ->first();

            $pricingPlan = app(HumanoPricingPlanResolver::class)->planByStripeProductId($productId);
            $planSlug = is_array($pricingPlan)
                ? strtolower(trim((string) ($pricingPlan['id'] ?? '')))
                : '';
            $planSlug = $planSlug !== '' ? $planSlug : null;

            $subscriptionType = 'mailer';
            if ($subscriptionProduct)
            {
                $subscriptionType = $subscriptionProduct->category ?? 'mailer';
            }
            if (is_array($pricingPlan))
            {
                $configuredType = trim((string) ($pricingPlan['subscription_type'] ?? ''));
                $subscriptionType = $configuredType !== ''
                    ? $configuredType
                    : (string) ($pricingPlan['id'] ?? $subscriptionType);
            }

            $metadata = $subscriptionMetadata;

            Log::info('Stripe subscription from checkout - data before save', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_subscription_status' => $stripeSubscription->status,
                'stripe_subscription_metadata_array' => $metadata,
            ]);

            $localSubscription = $team->subscriptions()
                ->where('stripe_id', $stripeSubscription->id)
                ->first();

            if (! $localSubscription)
            {
                $initialData = ! empty($metadata) ? $metadata : null;
                Log::info('Creating subscription with data', [
                    'data_value' => $initialData,
                ]);
                $team->subscriptions()->create([
                    'user_id' => $team->owner->id ?? $team->user_id,
                    'type' => $subscriptionType,
                    'stripe_id' => $stripeSubscription->id,
                    'stripe_status' => $stripeSubscription->status,
                    'stripe_price' => $priceId,
                    'quantity' => $stripeSubscription->items->data[0]->quantity,
                    'trial_ends_at' => $stripeSubscription->trial_end ? Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
                    'ends_at' => null,
                    'data' => $initialData,
                ]);
            } else
            {
                if (! empty($metadata))
                {
                    $existing = is_array($localSubscription->data) ? $localSubscription->data : [];
                    $merged = array_merge($existing, $metadata);

                    if ($merged !== $existing || $localSubscription->stripe_status !== $stripeSubscription->status)
                    {
                        $localSubscription->update([
                            'data' => $merged,
                            'stripe_status' => $stripeSubscription->status,
                        ]);
                    }
                } elseif ($localSubscription->stripe_status !== $stripeSubscription->status)
                {
                    $localSubscription->update(['stripe_status' => $stripeSubscription->status]);
                }
            }

            $localSubscription = $team->subscriptions()
                ->where('stripe_id', $stripeSubscription->id)
                ->first();

            if ($localSubscription)
            {
                app(PaymentLinkAffiliateTeamAttributionService::class)
                    ->applyReferrerToSessionSubscription($team, $session);
            }

            if ($fromPublicPaymentLinkCheckout && $localSubscription)
            {
                $existing = is_array($localSubscription->data) ? $localSubscription->data : [];
                if (($existing['payment_link_signup'] ?? null) !== '1' && ($existing['payment_link_signup'] ?? null) !== 1)
                {
                    $localSubscription->update([
                        'data' => array_merge($existing, ['payment_link_signup' => '1']),
                    ]);
                }
            }

            if ($planSlug !== null)
            {
                app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, $planSlug);
            }

            $isMailer = $subscriptionType === 'mailer'
                || (is_array($pricingPlan) && ($pricingPlan['catalog'] ?? '') === 'mailer');
            if ($isMailer)
            {
                $plan = EmailPlan::tryFromStripeProductId($productId);
                if ($plan)
                {
                    $team->assignEmailPlan($plan, $actingUserId);
                }
            }

            if ($subscriptionType === 'prospecting')
            {
                try
                {
                    $plan = ProspectPlan::fromStripePriceId($priceId);
                    $team->assignProspectPlan($plan, $actingUserId);
                } catch (\Exception $e)
                {
                    Log::warning('Could not assign prospect plan: '.$e->getMessage());
                }
            }
        } else
        {
            $this->applyProspectCreditPackFromSession($session, $team);
        }
    }

    /**
     * Apply prospect credits from a one-time checkout session (credit pack).
     */
    private function applyProspectCreditPackFromSession(Session $session, Team $team): void
    {
        if ($session->mode !== 'payment' || $session->payment_status !== 'paid')
        {
            return;
        }

        try
        {
            $lineItemsResponse = Session::allLineItems($session->id, ['expand' => ['data.price']]);
            $lineItems = $lineItemsResponse->data ?? [];
        } catch (\Exception $e)
        {
            Log::warning('Could not retrieve checkout session line items: '.$e->getMessage());

            return;
        }

        foreach ($lineItems as $item)
        {
            $priceId = $item->price->id ?? null;
            if (! $priceId)
            {
                continue;
            }

            $product = SubscriptionProduct::where('stripe_price', $priceId)->first();
            if (! $product || $product->category !== 'prospecting' || $product->recurring_interval)
            {
                continue;
            }

            $packs = config('prospects.credit_packs', []);
            $credits = (int) ($product->metadata['credits'] ?? $packs[$priceId] ?? 0);
            if ($credits > 0)
            {
                $team->addProspectCreditsFromPurchase($credits);
                Log::info('Prospect credits added from one-time purchase', [
                    'team_id' => $team->id,
                    'price_id' => $priceId,
                    'credits' => $credits,
                ]);
            }
        }
    }
}
