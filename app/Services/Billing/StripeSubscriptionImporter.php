<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use App\Services\HumanoPricingPlanResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Stripe;

class StripeSubscriptionImporter
{
    public function __construct(
        private readonly HumanoPricingPlanResolver $plans,
    ) {}

    /**
     * Persist a Stripe subscription that is not in the local table yet.
     */
    public function importMissing(string $stripeSubscriptionId): ?Subscription
    {
        $stripeSubscriptionId = trim($stripeSubscriptionId);

        $existing = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();

        if ($existing?->team !== null)
        {
            return $existing;
        }

        $stripeSubscription = $this->retrieve($stripeSubscriptionId);

        if ($stripeSubscription === null)
        {
            return null;
        }

        $imported = $this->importRetrieved($stripeSubscription);

        if ($imported === null)
        {
            throw ValidationException::withMessages([
                'subscription_code' => __('Encontramos la suscripción en Stripe, pero ese cliente no está cargado en el sistema.'),
            ]);
        }

        return $imported;
    }

    public function importRetrieved(\Stripe\Subscription $stripeSubscription): ?Subscription
    {
        $customerId = $this->customerId($stripeSubscription);

        if ($customerId === '')
        {
            return null;
        }

        $team = Team::findByStripeCustomerId($customerId);

        if ($team === null)
        {
            return null;
        }

        $item = $stripeSubscription->items->data[0] ?? null;
        $priceId = trim((string) ($item->price->id ?? ''));
        $rawProduct = $item->price->product ?? null;
        $productId = is_string($rawProduct) ? $rawProduct : trim((string) ($rawProduct->id ?? ''));

        $existing = Subscription::query()->where('stripe_id', $stripeSubscription->id)->first();

        if ($existing !== null)
        {
            if ((int) ($existing->team_id ?? 0) === 0)
            {
                $existing->forceFill([
                    'team_id' => $team->id,
                    'user_id' => $team->owner?->id ?? $team->user_id,
                    'type' => $this->subscriptionType($priceId, $productId),
                    'stripe_status' => (string) $stripeSubscription->status,
                    'stripe_price' => $priceId !== '' ? $priceId : $existing->stripe_price,
                ])->save();
            }

            return $existing->fresh();
        }

        return $team->subscriptions()->create([
            'user_id' => $team->owner?->id ?? $team->user_id,
            'type' => $this->subscriptionType($priceId, $productId),
            'stripe_id' => $stripeSubscription->id,
            'stripe_status' => (string) $stripeSubscription->status,
            'stripe_price' => $priceId !== '' ? $priceId : null,
            'quantity' => (int) ($item->quantity ?? 1),
            'trial_ends_at' => $stripeSubscription->trial_end
                ? Carbon::createFromTimestamp((int) $stripeSubscription->trial_end)
                : null,
            'ends_at' => null,
        ]);
    }

    private function retrieve(string $stripeSubscriptionId): ?\Stripe\Subscription
    {
        $secret = trim((string) config('cashier.secret'));

        if ($secret === '' || $stripeSubscriptionId === '')
        {
            return null;
        }

        Stripe::setApiKey($secret);

        try
        {
            return \Stripe\Subscription::retrieve([
                'id' => $stripeSubscriptionId,
                'expand' => ['items.data.price.product'],
            ]);
        } catch (InvalidRequestException)
        {
            return null;
        } catch (ApiErrorException $exception)
        {
            Log::warning('Affiliate claim could not retrieve Stripe subscription', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'subscription_code' => __('No pudimos consultar esa suscripción en Stripe.'),
            ]);
        }
    }

    private function customerId(\Stripe\Subscription $stripeSubscription): string
    {
        $customer = $stripeSubscription->customer;

        if (is_string($customer))
        {
            return trim($customer);
        }

        return trim((string) ($customer->id ?? ''));
    }

    private function subscriptionType(string $priceId, string $productId): string
    {
        $subscriptionProduct = null;

        if ($priceId !== '' || $productId !== '')
        {
            $subscriptionProduct = SubscriptionProduct::query()
                ->where(function ($query) use ($priceId, $productId): void
                {
                    if ($priceId !== '')
                    {
                        $query->orWhere('stripe_price', $priceId);
                    }

                    if ($productId !== '')
                    {
                        $query->orWhere('stripe_product', $productId)
                            ->orWhere('stripe_id', $productId);
                    }
                })
                ->first();
        }

        $pricingPlan = $productId !== '' ? $this->plans->planByStripeProductId($productId) : null;
        $subscriptionType = (string) ($subscriptionProduct->category ?? 'default');

        if (is_array($pricingPlan))
        {
            $configuredType = trim((string) ($pricingPlan['subscription_type'] ?? ''));
            $subscriptionType = $configuredType !== ''
                ? $configuredType
                : (string) ($pricingPlan['id'] ?? $subscriptionType);
        }

        return $subscriptionType !== '' ? $subscriptionType : 'default';
    }
}
