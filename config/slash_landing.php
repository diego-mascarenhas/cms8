<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slash landing section visibility
    |--------------------------------------------------------------------------
    |
    | Toggle sections on /homes/slash while content is still being prepared.
    |
    */

    'show_trust_section' => env('SLASH_LANDING_SHOW_TRUST', false),

    'show_plan_stories_section' => env('SLASH_LANDING_SHOW_PLAN_STORIES', false),

    'compliance_url' => env('SLASH_LANDING_COMPLIANCE_URL', 'https://revisionalpha.com/conformidad'),

];
