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
    | When set, quote emails and API preview_url point at the frontend
    | (/p/budget/{token}) so the team logo from NEXT_PUBLIC_LOGO_URL is used.
    | Empty keeps the Humano /p/budget/{token} page.
    |
    */
    'budget_preview_base_url' => env('BUDGET_PREVIEW_BASE_URL'),

];
