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

    'show_plan_stories_section' => env('SLASH_LANDING_SHOW_PLAN_STORIES', true),

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

    /*
    | Primeros tutoriales de onboarding destacados en la sección «En acción».
    | Orden = playlist (sin el trailer). youtube_id vacío oculta esa tarjeta.
    */
    'onboarding_featured_videos' => [
        [
            'youtube_id' => env('SLASH_LANDING_YOUTUBE_VIDEO_01', 'QXVZJUaBYh4'),
            'title' => 'Configuración del negocio',
            'subtitle' => 'Paso 1',
            'poster' => 'plans/assistant.png',
        ],
        [
            'youtube_id' => env('SLASH_LANDING_YOUTUBE_VIDEO_02', 'uju-eMnSiO0'),
            'title' => 'Conectar WhatsApp',
            'subtitle' => 'Paso 2',
            'poster' => 'plans/hunter.png',
        ],
        [
            'youtube_id' => env('SLASH_LANDING_YOUTUBE_VIDEO_03', 'luwXe0wu37E'),
            'title' => 'Chat, contactos y módulos',
            'subtitle' => 'Paso 3',
            'poster' => 'plans/business.png',
        ],
    ],

];
