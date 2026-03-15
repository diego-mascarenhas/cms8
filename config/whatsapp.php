<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "twilio", "local"
    |
    | - twilio: Use Twilio WhatsApp API (requires TWILIO_* env vars).
    | - local:  Use self-hosted Node.js service (Baileys) with QR linking.
    |
    */
    'driver' => env('WHATSAPP_DRIVER', 'twilio'),

    /*
    |--------------------------------------------------------------------------
    | Local WhatsApp Service (Baileys / Node.js)
    |--------------------------------------------------------------------------
    |
    | Base URL of the Node.js WhatsApp service. Used when driver is "local".
    | For one number per team without disconnecting others, run one Node instance
    | per team and set team setting "whatsapp_service_url" per team (e.g. port
    | 3000 for team 1, 3001 for team 2). Fallback: this base_url.
    |
    */
    'local' => [
        'base_url' => env('WHATSAPP_LOCAL_BASE_URL', 'http://localhost:3000'),
        'webhook_secret' => env('WHATSAPP_LOCAL_WEBHOOK_SECRET'),
    ],

];
