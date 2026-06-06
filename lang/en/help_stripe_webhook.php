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
        'invoice.paid',
        'invoice.payment_succeeded',
        'invoice.updated',
        'invoice.payment_failed',
    ],

    'events_recommended' => [
        'customer.updated',
        'payment_method.automatically_updated',
    ],

    'events_checkout' => 'If the app creates Stripe Checkout sessions for subscriptions or registration billing, also add:',
    'events_checkout_item' => 'checkout.session.completed',

    'dashboard_heading' => 'Add events in the Stripe Dashboard',
    'dashboard_intro' => 'If your webhook only has subscription events and invoice.payment_succeeded, you are missing events required for external transfers and status updates. Follow these steps in production (Live mode):',
    'dashboard_steps' => [
        'Open Developers → Webhooks (or Event destinations in Workbench): https://dashboard.stripe.com/webhooks',
        'Confirm the top selector is Live, not Test.',
        'Open the endpoint that points to your app (POST /stripe/webhook).',
        'Click Edit destination, Update details, or Configure.',
        'Under Events to send / Select events, click Add events or + Select events.',
        'Find the Invoice category and enable invoice.paid and invoice.updated (keep your existing events).',
        'Optional: add invoice.payment_failed.',
        'Save with Update endpoint / Save.',
    ],
    'dashboard_listening_heading' => 'Recommended minimum list (Listening to)',
    'dashboard_listening_customer' => 'Customer',
    'dashboard_listening_invoice' => 'Invoice',

    'invoice_paid_heading' => 'Why invoice.paid is required',
    'invoice_paid_intro' => 'invoice.payment_succeeded does not cover every collection path. Humano uses invoice.paid to mark invoices as collected when Stripe records external transfers or other payments without a ch_ charge.',
    'invoice_paid_table_heading' => 'Invoice event comparison',
    'invoice_paid_table_col_type' => 'Collection type',
    'invoice_paid_table_col_succeeded' => 'invoice.payment_succeeded',
    'invoice_paid_table_col_paid' => 'invoice.paid',
    'invoice_paid_table_rows' => [
        ['Card / normal charge (ch_)', 'Yes', 'Yes'],
        ['External transfer marked in Stripe', 'Sometimes no', 'Yes'],
    ],
    'invoice_updated_note' => 'invoice.updated is a fallback: when an invoice becomes paid, void, or uncollectible, Humano refreshes staging and imports to the core invoice.',

    'verify_heading' => 'Verify it works',
    'verify_steps' => [
        'On the webhook page, open Event deliveries / Recent deliveries.',
        'After a real payment, you should see invoice.paid with HTTP 200.',
        'Manual test: Send test webhook → choose invoice.paid → confirm 200 OK.',
        'If you see 4xx or 5xx, check the endpoint URL, STRIPE_WEBHOOK_SECRET, and that the queue worker is running (the job is queued).',
    ],

    'fallback_heading' => 'Automatic fallback if the webhook is missed',
    'fallback_intro' => 'Humano runs scheduled tasks that sync invoices and create missing payments. They do not replace webhooks, but fix drift within minutes:',
    'fallback_items' => [
        'stripe:sync-invoices — every 10 min (refreshes invoice_syncs from the API)',
        'invoice-syncs:import-stripe --reconcile — every 10 min (updates balance and status on invoices)',
        'invoices:reconcile-stripe-collected-payments — at :20 and :50 (creates missing payments)',
    ],

    'scope_heading' => 'Destination scope and API version',
    'scope_body' => 'Use “Your account” unless you operate as a Stripe Connect platform receiving events for connected accounts. Pick an API version compatible with your Stripe PHP SDK; if payloads differ, align the dashboard version with your stack.',

    'secret_heading' => 'Signing secret',
    'secret_body' => 'After creating the endpoint, copy the signing secret into STRIPE_WEBHOOK_SECRET (or the matching stripe_accounts.{category}.webhook_secret for category URLs). The route is excluded from CSRF verification.',
];
