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

    'youtube_channel_url' => env('SLASH_LANDING_YOUTUBE_CHANNEL_URL', 'https://www.youtube.com/@revisionalpha'),

    /*
    | Playlist pública «Humano • Onboarding». Si está definida, los enlaces de las
    | landings apuntan aquí en lugar del canal. Pega la URL completa o solo el ID (PL…).
    */
    'youtube_onboarding_playlist_url' => env(
        'SLASH_LANDING_YOUTUBE_PLAYLIST_URL',
        'https://www.youtube.com/playlist?list=PLebHHjcT7KEc',
    ),

    'youtube_onboarding_playlist_id' => env('SLASH_LANDING_YOUTUBE_PLAYLIST_ID'),

];
