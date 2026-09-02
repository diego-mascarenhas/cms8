<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public projects funnel (optional fallback team)
    |--------------------------------------------------------------------------
    |
    | Frontends identify the destination team with X-Team-Token (team API
    | token). PROJECTS_FUNNEL_TEAM_ID is only used when no token is sent.
    |
    */
    'funnel_team_id' => env('PROJECTS_FUNNEL_TEAM_ID'),

    /*
    |--------------------------------------------------------------------------
    | Public budget preview (idoneo-estimator)
    |--------------------------------------------------------------------------
    |
    | When set, quote emails and API preview_url point at that frontend
    | (/p/budget/{token}) so NEXT_PUBLIC_LOGO_URL of that deploy is used.
    | The requesting Origin (presu.humano.app, estimator.idoneo.dev, …) wins
    | when it is an estimator frontend, and is stored on the project.
    | Empty + no Origin keeps the cms8 /p/budget/{token} page.
    |
    */
    'budget_preview_base_url' => env('BUDGET_PREVIEW_BASE_URL'),

    'budget_preview_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BUDGET_PREVIEW_ALLOWED_ORIGINS', 'https://presu.humano.app,https://estimator.idoneo.dev')),
    ))),

];
