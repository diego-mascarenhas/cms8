<?php

namespace App\Services\Stripe;

use App\Models\SLA;
use App\Models\SLAAcceptance;
use App\Models\Subscription;
use App\Models\SubscriptionProduct;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SlaAcceptanceFromStripeSubscriptionPersister
{
    public function __construct(private readonly StripeSubscriptionService $stripeSubscriptions) {}

    /**
     * Persist SLA acceptance from Stripe subscription metadata (token + accepted_at).
     *
     * Expects metadata keys set on the Payment Link / Checkout:
     * - sla_acceptance_token
     * Optionally stamps sla_accepted_at back onto the Stripe subscription.
     *
     * @param  array<string, mixed>  $stripeSubscription
     */
    public function persistFromStripeSubscription(array $stripeSubscription): ?SLAAcceptance
    {
        $metadata = $this->metadataAsArray($stripeSubscription['metadata'] ?? null);
        $token = trim((string) ($metadata['sla_acceptance_token'] ?? ''));

        if ($token === '')
        {
            return null;
        }

        if (! empty($metadata['sla_accepted_at']))
        {
            $existing = SLAAcceptance::query()->where('token', $token)->first();

            return $existing;
        }

        $acceptedAt = Carbon::now();
        $customerId = (string) ($stripeSubscription['customer'] ?? '');
        $stripeSubscriptionId = (string) ($stripeSubscription['id'] ?? '');
        $priceId = $stripeSubscription['items']['data'][0]['price']['id'] ?? null;
        $productId = $stripeSubscription['items']['data'][0]['price']['product'] ?? null;

        $team = $customerId !== '' ? Team::findByStripeCustomerId($customerId) : null;
        $localSubscription = $stripeSubscriptionId !== ''
            ? Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first()
            : null;

        $sla = $this->resolveSla($priceId, is_string($productId) ? $productId : null);

        $acceptance = SLAAcceptance::query()->firstOrNew(['token' => $token]);

        if ($acceptance->accepted_at)
        {
            $this->stampStripeMetadata($stripeSubscriptionId, $metadata, $token, $acceptance);

            return $acceptance;
        }

        if (! $sla && ! $acceptance->exists)
        {
            Log::info('SLA acceptance token present but no local SLA/product; stamping Stripe metadata only', [
                'token' => $token,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_price' => $priceId,
                'stripe_product' => $productId,
            ]);

            $this->stampStripeMetadata($stripeSubscriptionId, $metadata, $token, null, $acceptedAt);

            return null;
        }

        if ($sla)
        {
            $acceptance->sla_id = $sla->id;
        }

        $acceptance->fill([
            'subscription_id' => $localSubscription?->id ?? $acceptance->subscription_id,
            'accepted_by_email' => $acceptance->accepted_by_email
                ?: ($team?->owner?->email ?? $metadata['customer_email'] ?? null),
            'accepted_by_name' => $acceptance->accepted_by_name
                ?: ($team?->owner?->name ?? $team?->name ?? $metadata['customer_name'] ?? null),
            'accepted_at' => $acceptedAt,
        ]);
        $acceptance->save();

        $this->stampStripeMetadata($stripeSubscriptionId, $metadata, $token, $acceptance, $acceptedAt);

        Log::info('SLA acceptance persisted from Stripe subscription', [
            'acceptance_id' => $acceptance->id,
            'sla_id' => $acceptance->sla_id,
            'token' => $token,
            'accepted_at' => $acceptedAt->toIso8601String(),
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return $acceptance;
    }

    private function resolveSla(?string $priceId, ?string $productId): ?SLA
    {
        $product = null;

        if ($priceId)
        {
            $product = SubscriptionProduct::query()->where('stripe_price', $priceId)->first();
        }

        if (! $product && $productId)
        {
            $product = SubscriptionProduct::query()
                ->where('stripe_product', $productId)
                ->orWhere('stripe_id', $productId)
                ->first();
        }

        return $product?->sla;
    }

    /**
     * @param  array<string, mixed>|object|null  $metadata
     * @return array<string, string>
     */
    private function metadataAsArray(array|object|null $metadata): array
    {
        if ($metadata === null)
        {
            return [];
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray'))
        {
            $metadata = $metadata->toArray();
        }

        $out = [];
        foreach ((array) $metadata as $key => $value)
        {
            if (is_scalar($value) || $value === null)
            {
                $out[(string) $key] = (string) ($value ?? '');
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $currentMetadata
     */
    private function stampStripeMetadata(
        string $stripeSubscriptionId,
        array $currentMetadata,
        string $token,
        ?SLAAcceptance $acceptance,
        ?Carbon $acceptedAt = null,
    ): void {
        if ($stripeSubscriptionId === '')
        {
            return;
        }

        $acceptedAtString = $acceptance?->accepted_at?->toIso8601String()
            ?? $acceptedAt?->toIso8601String();

        if (! $acceptedAtString)
        {
            return;
        }

        $stripeMetadata = $currentMetadata;
        $stripeMetadata['sla_acceptance_token'] = $token;
        $stripeMetadata['sla_accepted_at'] = $acceptedAtString;

        if ($acceptance)
        {
            $stripeMetadata['sla_acceptance_id'] = (string) $acceptance->id;
            if ($acceptance->sla_id)
            {
                $stripeMetadata['sla_acceptance_date_'.$acceptance->sla_id] = $acceptedAtString;
            }
        }

        try
        {
            $this->stripeSubscriptions->updateMetadata($stripeSubscriptionId, $stripeMetadata);
        } catch (Throwable $e)
        {
            Log::error('Failed to stamp SLA acceptance metadata on Stripe subscription', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
