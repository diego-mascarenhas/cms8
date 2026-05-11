<?php

namespace App\Services\Stripe;

use Stripe\Checkout\Session;

final class StripeCheckoutSessionLogFormatter
{
    /**
     * Safe subset of Stripe Checkout Session fields for application logs (no card numbers).
     *
     * @return array<string, mixed>
     */
    public static function toLogContext(Session $session): array
    {
        $customer = $session->customer ?? null;
        $subscription = $session->subscription ?? null;
        $details = $session->customer_details ?? null;
        $paymentIntent = $session->payment_intent ?? null;

        $addressCountry = null;
        if ($details && isset($details->address) && is_object($details->address))
        {
            $addressCountry = $details->address->country ?? null;
        }

        return [
            'stripe_checkout_session_id' => $session->id ?? null,
            'object' => $session->object ?? null,
            'status' => $session->status ?? null,
            'mode' => $session->mode ?? null,
            'payment_status' => $session->payment_status ?? null,
            'livemode' => $session->livemode ?? null,
            'currency' => $session->currency ?? null,
            'amount_total' => $session->amount_total ?? null,
            'amount_subtotal' => $session->amount_subtotal ?? null,
            'customer' => is_string($customer) ? $customer : (is_object($customer) ? ($customer->id ?? null) : null),
            'subscription' => is_string($subscription) ? $subscription : (is_object($subscription) ? ($subscription->id ?? null) : null),
            'payment_intent' => is_string($paymentIntent) ? $paymentIntent : (is_object($paymentIntent) ? ($paymentIntent->id ?? null) : null),
            'client_reference_id' => $session->client_reference_id ?? null,
            'metadata' => $session->metadata ? $session->metadata->toArray() : [],
            'customer_details' => $details ? [
                'email' => $details->email ?? null,
                'name' => $details->name ?? null,
                'address_country' => $addressCountry,
            ] : null,
        ];
    }
}
