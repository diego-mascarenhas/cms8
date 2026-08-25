<?php

namespace App\Helpers;

/**
 * Digits-only session key for WhatsApp shopping carts
 * (inbound commands vs assistant tool add_to_whatsapp_cart).
 *
 * Normalizes Spanish mobiles stored/sent as 9 national digits vs 34 + 9 international.
 */
class WhatsAppCartSessionKey
{
    /**
     * Digits-only key stored on shopping_carts.session_key for WhatsApp shoppers.
     */
    public static function fromPhone(string $phone): string
    {
        $d = preg_replace('/[^0-9]/', '', $phone);
        if ($d === '')
        {
            return '';
        }

        if (strlen($d) === 9 && ! str_starts_with($d, '34'))
        {
            return '34'.$d;
        }

        return $d;
    }
}
