<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cola y conexión
    |--------------------------------------------------------------------------
    |
    | Los envíos de campaña (MessageDelivery con campaign_id) usan la cola campaign.
    | Mensajes sueltos, reenvíos y pruebas usan mailer. Configura tus workers como prefieras, p. ej.:
    |
    |   php artisan queue:work redis --queue=campaign --tries=3
    |   php artisan queue:work redis --queue=mailer --tries=3
    |
    | Un solo proceso con prioridad: --queue=mailer,campaign
    |
    */

    'connection' => env('MESSAGE_DELIVERY_QUEUE_CONNECTION'),

    /** Cola cuando se despacha el job fuera del dispatcher (solo respaldo). */
    'fallback_queue' => env('MESSAGE_DELIVERY_FALLBACK_QUEUE', 'mailer'),

    'campaign' => [
        'queue' => env('MESSAGE_DELIVERY_CAMPAIGN_QUEUE', 'campaign'),
        'dispatch_jitter_seconds' => [
            'min' => (int) env('MESSAGE_DELIVERY_CAMPAIGN_JITTER_MIN', 2),
            'max' => (int) env('MESSAGE_DELIVERY_CAMPAIGN_JITTER_MAX', 8),
        ],
    ],

    'message' => [
        'queue' => env('MESSAGE_DELIVERY_MESSAGE_QUEUE', 'mailer'),
        'dispatch_jitter_seconds' => [
            'min' => (int) env('MESSAGE_DELIVERY_MESSAGE_JITTER_MIN', 1),
            'max' => (int) env('MESSAGE_DELIVERY_MESSAGE_JITTER_MAX', 3),
        ],
    ],
];
