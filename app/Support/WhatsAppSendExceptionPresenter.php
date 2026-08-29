<?php

namespace App\Support;

use App\Exceptions\WhatsAppSessionWindowClosedException;

/**
 * Maps WhatsApp gateway / HTTP failures to short Spanish messages for the UI.
 */
class WhatsAppSendExceptionPresenter
{
    public static function messageForUser(\Throwable $e): string
    {
        $raw = trim($e->getMessage());
        $lower = mb_strtolower($raw);

        if (
            str_contains($lower, 'not configured')
            || str_contains($lower, 'whatsapp_local_base_url')
            || str_contains($lower, 'local whatsapp service is not configured')
        ) {
            return __('whatsapp.send.error.local_not_configured');
        }

        if (
            str_contains($lower, 'curl error 7')
            || str_contains($lower, 'could not connect to server')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'connection refused')
            || str_contains($lower, 'connection reset')
        ) {
            return __('whatsapp.send.error.local_unreachable');
        }

        if (
            str_contains($lower, 'curl error 28')
            || str_contains($lower, 'operation timed out')
            || str_contains($lower, 'timed out')
        ) {
            return __('whatsapp.send.error.local_timeout');
        }

        if (
            str_contains($lower, 'not ready to send')
            || str_contains($lower, 'connection settling')
        ) {
            return __('whatsapp.send.error.not_ready');
        }

        if (
            str_contains($lower, 'not connected')
            || str_contains($lower, 'scan qr')
        ) {
            return __('whatsapp.send.error.not_connected');
        }

        if (str_contains($lower, 'local whatsapp send failed'))
        {
            return __('whatsapp.send.error.local_http_rejected');
        }

        if ($e instanceof WhatsAppSessionWindowClosedException || str_contains($raw, '63016'))
        {
            return __('whatsapp.send.error.session_window_closed');
        }

        return __('whatsapp.send.error.generic');
    }
}
