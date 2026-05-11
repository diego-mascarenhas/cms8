<?php

return [
    'page_title' => 'Stripe webhooks — Help',
    'title' => 'Stripe webhooks (Humano)',
    'intro' => 'In the Stripe Dashboard (Developers → Webhooks), create an endpoint that sends events to this application. Humano uses Laravel Cashier plus custom handlers for subscriptions, invoices, and affiliate commissions.',

    'url_heading' => 'Endpoint URL',
    'url_method' => 'Method: POST',
    'url_path_label' => 'Path on your site',
    'url_full_example' => 'Full URL (replace the domain with your public site, same as APP_URL):',
    'url_https' => 'Stripe requires a publicly reachable HTTPS URL in staging and production.',

    'local_heading' => 'Local development',
    'local_body' => 'Stripe cannot call https://*.test from the internet. Use the Stripe CLI and put the signing secret it prints into your environment (for example STRIPE_WEBHOOK_SECRET):',

    'multi_heading' => 'Optional: per-category Stripe accounts',
    'multi_body' => 'If you use separate Stripe accounts per integration category, add one endpoint per category. Allowed categories: mentoring, mailer, prospecting, hosting, support. Example:',

    'events_heading' => 'Events to enable',
    'events_intro' => 'Select at least the following. They cover Cashier subscription sync, your custom invoice logic, and plan changes:',

    'events_recommended_heading' => 'Also recommended',

    'events_required' => [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
    ],

    'events_recommended' => [
        'customer.updated',
        'payment_method.automatically_updated',
    ],

    'events_checkout' => 'If the app creates Stripe Checkout sessions for subscriptions or registration billing, also add:',
    'events_checkout_item' => 'checkout.session.completed',

    'scope_heading' => 'Destination scope and API version',
    'scope_body' => 'Use “Your account” unless you operate as a Stripe Connect platform receiving events for connected accounts. Pick an API version compatible with your Stripe PHP SDK; if payloads differ, align the dashboard version with your stack.',

    'secret_heading' => 'Signing secret',
    'secret_body' => 'After creating the endpoint, copy the signing secret into STRIPE_WEBHOOK_SECRET (or the matching stripe_accounts.{category}.webhook_secret for category URLs). The route is excluded from CSRF verification.',
];
