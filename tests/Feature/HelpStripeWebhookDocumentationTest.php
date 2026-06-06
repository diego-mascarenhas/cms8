<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpStripeWebhookDocumentationTest extends TestCase
{
    public function test_stripe_webhook_help_page_is_public(): void
    {
        $response = $this->get('/help/stripe-webhook');

        $response->assertOk();
        $response->assertSee('/stripe/webhook', false);
        $response->assertSee('customer.subscription', false);
        $response->assertSee('invoice.paid', false);
        $response->assertSee('invoice.updated', false);
    }
}
