<?php

namespace App\Support;

/**
 * Session flags after public Humano plan Payment Link checkout.
 */
final class HumanoPublicPaymentLinkCheckout
{
    /**
     * Show the WhatsApp QR CTA on the dashboard next to the business configuration prompt.
     */
    public const SESSION_SHOW_DASHBOARD_WHATSAPP_QR_CTA = 'humano_dashboard_post_payment_link_whatsapp_qr';

    private function __construct() {}
}
