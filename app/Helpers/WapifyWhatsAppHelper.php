<?php

namespace App\Helpers;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class WapifyWhatsAppHelper
{
    private const DEFAULT_PHONE = '34613194131';

    /**
     * PNG data URI for the given payload (same stack as TwilioService QR generation).
     */
    public static function qrDataUri(string $data): string
    {
        $qrcode = new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
        ]));

        return $qrcode->render($data);
    }

    /**
     * @return array{qr_data: string, web_url: string, api_url: string}
     */
    public static function resolve(): array
    {
        $envLink = trim((string) config('app.wapify_whatsapp_link', ''));
        $phoneConfig = preg_replace('/\D/', '', (string) config('app.wapify_whatsapp_phone', ''));
        $text = (string) config('app.wapify_whatsapp_text', 'Hola!');

        $phoneFromLink = '';
        $textFromLink = null;
        if ($envLink !== '')
        {
            if (preg_match('/[?&]phone=([0-9]+)/i', $envLink, $m))
            {
                $phoneFromLink = $m[1];
            }
            if (preg_match('/[?&]text=([^&]*)/i', $envLink, $m2))
            {
                $textFromLink = rawurldecode(str_replace('+', ' ', $m2[1]));
            }
        }

        $phone = $phoneFromLink !== '' ? $phoneFromLink : $phoneConfig;
        if ($phone === '')
        {
            $phone = self::DEFAULT_PHONE;
        }
        if ($textFromLink !== null && $textFromLink !== '')
        {
            $text = $textFromLink;
        }

        $apiUrl = '';
        if ($envLink !== '')
        {
            $apiUrl = $envLink;
        } else
        {
            $apiUrl = 'https://api.whatsapp.com/send/?phone='.$phone.'&text='.rawurlencode($text);
        }

        $webUrl = 'https://web.whatsapp.com/send?phone='.$phone.'&text='.rawurlencode($text);

        return [
            'qr_data' => $apiUrl,
            'web_url' => $webUrl,
            'api_url' => $apiUrl,
        ];
    }
}
