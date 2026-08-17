<?php

namespace App\Http\Controllers;

use App\Services\StripeAccountResolver;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\HttpFoundation\Response;

class CategoryStripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook for a specific account category.
     * Verifies signature with the category's webhook secret, then delegates to the main webhook handler.
     */
    public function handleWebhook(Request $request, string $category): Response
    {
        $category = StripeAccountResolver::normalizeCategory($category);
        $secret = StripeAccountResolver::webhookSecretForCategory($category);

        if (empty($secret))
        {
            \Log::warning("Stripe webhook: no secret configured for category {$category}");

            return new Response('Webhook secret not configured', 500);
        }

        try
        {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $secret,
                (int) config('cashier.webhook.tolerance', 300),
            );
        } catch (SignatureVerificationException $e)
        {
            \Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

            return new Response('Invalid signature', 400);
        }

        return app(StripeWebhookController::class)->handleWebhook($request);
    }
}
