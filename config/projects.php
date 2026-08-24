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

];
