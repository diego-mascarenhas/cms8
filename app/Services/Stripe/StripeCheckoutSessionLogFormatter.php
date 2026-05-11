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
        $invoice = $session->invoice ?? null;
        $setupIntent = $session->setup_intent ?? null;

        $addressCountry = null;
        if ($details && isset($details->address) && is_object($details->address))
        {
            $addressCountry = $details->address->country ?? null;
        }

        $shippingCountry = null;
        $shippingDetails = $session->shipping_details ?? null;
        if ($shippingDetails && isset($shippingDetails->address) && is_object($shippingDetails->address))
        {
            $shippingCountry = $shippingDetails->address->country ?? null;
        }

        $paymentMethodTypes = $session->payment_method_types ?? null;
        if (is_array($paymentMethodTypes))
        {
            $paymentMethodTypes = array_values($paymentMethodTypes);
        } elseif (is_object($paymentMethodTypes) && method_exists($paymentMethodTypes, 'toArray'))
        {
            $paymentMethodTypes = array_values($paymentMethodTypes->toArray());
        } else
        {
            $paymentMethodTypes = null;
        }

        return [
            'stripe_checkout_session_id' => $session->id ?? null,
            'object' => $session->object ?? null,
            'status' => $session->status ?? null,
            'mode' => $session->mode ?? null,
            'payment_status' => $session->payment_status ?? null,
            'livemode' => $session->livemode ?? null,
            'locale' => $session->locale ?? null,
            'created' => $session->created ?? null,
            'expires_at' => $session->expires_at ?? null,
            'currency' => $session->currency ?? null,
            'amount_total' => $session->amount_total ?? null,
            'amount_subtotal' => $session->amount_subtotal ?? null,
            'shipping_amount' => is_object($session->shipping_cost ?? null)
                ? ($session->shipping_cost->amount_total ?? null)
                : null,
            'shipping_country' => $shippingCountry,
            'customer' => is_string($customer) ? $customer : (is_object($customer) ? ($customer->id ?? null) : null),
            'customer_email' => $session->customer_email ?? null,
            'subscription' => is_string($subscription) ? $subscription : (is_object($subscription) ? ($subscription->id ?? null) : null),
            'invoice' => is_string($invoice) ? $invoice : (is_object($invoice) ? ($invoice->id ?? null) : null),
            'payment_intent' => is_string($paymentIntent) ? $paymentIntent : (is_object($paymentIntent) ? ($paymentIntent->id ?? null) : null),
            'setup_intent' => is_string($setupIntent) ? $setupIntent : (is_object($setupIntent) ? ($setupIntent->id ?? null) : null),
            'payment_link' => $session->payment_link ?? null,
            'payment_method_types' => $paymentMethodTypes,
            'client_reference_id' => $session->client_reference_id ?? null,
            'metadata' => ($session->__isset('metadata') && $session->metadata)
                ? $session->metadata->toArray()
                : [],
            'customer_details' => $details ? [
                'email' => $details->email ?? null,
                'name' => $details->name ?? null,
                'address_country' => $addressCountry,
            ] : null,
            'line_items' => self::summarizeLineItems($session),
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function summarizeLineItems(Session $session): ?array
    {
        $lineItems = $session->line_items ?? null;
        $data = $lineItems && isset($lineItems->data) ? $lineItems->data : null;
        if ($data === null)
        {
            return null;
        }

        $rows = is_array($data) ? $data : iterator_to_array($data, false);

        $out = [];
        foreach (array_slice($rows, 0, 20) as $item)
        {
            if (! is_object($item))
            {
                continue;
            }

            $price = $item->price ?? null;
            $productRef = is_object($price) ? ($price->product ?? null) : null;
            $productId = is_string($productRef) ? $productRef : (is_object($productRef) ? ($productRef->id ?? null) : null);

            $out[] = [
                'quantity' => $item->quantity ?? null,
                'price_id' => is_object($price) ? ($price->id ?? null) : null,
                'product_id' => $productId,
                'amount_subtotal' => $item->amount_subtotal ?? null,
                'amount_total' => $item->amount_total ?? null,
                'currency' => $item->currency ?? null,
            ];
        }

        return $out === [] ? null : $out;
    }
}
