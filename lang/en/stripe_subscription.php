<?php

return [
    'title' => 'Subscriptions',
    'subtitle' => 'Client subscriptions synced from Stripe',
    'sync_button' => 'Sync from Stripe',
    'sync_success' => 'Subscriptions synced from Stripe: :count processed.',
    'errors' => [
        'no_team' => 'No team selected.',
        'no_stripe_secret' => 'Configure the team Stripe secret key in team settings to sync. You can use test API keys (sk_test_…).',
    ],
    'columns' => [
        'customer_name' => 'Customer',
        'customer_email' => 'Email',
        'plan_name' => 'Plan',
        'status' => 'Status',
        'amount_total' => 'Amount',
        'current_period_end' => 'Next',
        'actions' => 'Actions',
    ],
    'open_service' => 'Open service',
    'open_client' => 'Open client',
    'open_contact' => 'Open contact',
    'status' => [
        'active' => 'Active',
        'canceled' => 'Canceled',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Incomplete (expired)',
        'past_due' => 'Past due',
        'paused' => 'Paused',
        'trialing' => 'Trialing',
        'unpaid' => 'Unpaid',
    ],
];
