<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'whatsapp/webhook',
        'whatsapp/status',
        'whatsapp/fallback',
        'whatsapp/webhook/*',
        'whatsapp/status/*',
        'whatsapp/fallback/*',
        'twilio/webhook',
        'webhook/whatsapp-local',
        'webhook/whatsapp-local/*',
        'webhook/*',
        'chat/whatsapp-linked',
        'lead',  // form submissions and external lead sources
        'stripe/webhook', // Stripe webhook handler (default and per-category)
        'stripe/webhook/*',
    ];
}
