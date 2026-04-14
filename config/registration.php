<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration billing mode
    |--------------------------------------------------------------------------
    |
    | free     — Standard registration; platform access does not depend on Stripe.
    | checkout — After register, user is sent to Stripe Checkout immediately.
    | gate     — After register, user may browse only until Stripe payment is completed
    |            (billing gate + optional onboarding steps).
    |
    */

    'mode' => strtolower((string) env('REGISTRATION_MODE', 'free')),

    /*
    |--------------------------------------------------------------------------
    | Stripe product: platform access (registration + default subscription)
    |--------------------------------------------------------------------------
    |
    | Same catalog row is used for paid registration (checkout / gate) and as
    | the default subscription product when billing/checkout is opened without
    | another plan or product_id, as long as this value matches subscription_products.
    |
    */

    'stripe_product_id' => (string) env('REGISTRATION_STRIPE_PRODUCT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Demo teams (skip registration billing gate)
    |--------------------------------------------------------------------------
    |
    | Comma-separated team IDs that should not be forced through registration
    | payment (e.g. seeded demo workspace). Example: 1 or 1,2
    |
    */

    'demo_team_ids' => collect(explode(',', (string) env('REGISTRATION_DEMO_TEAM_IDS', '')))
        ->map(static fn (string $id): int => (int) trim($id))
        ->filter(static fn (int $id): bool => $id > 0)
        ->unique()
        ->values()
        ->all(),

];
