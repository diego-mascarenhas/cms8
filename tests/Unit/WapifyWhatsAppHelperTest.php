<?php

namespace Tests\Unit;

use App\Helpers\WapifyWhatsAppHelper;
use Tests\TestCase;

class WapifyWhatsAppHelperTest extends TestCase
{
    public function test_resolve_uses_default_phone_when_config_empty(): void
    {
        config([
            'app.wapify_whatsapp_link' => '',
            'app.wapify_whatsapp_phone' => '',
            'app.wapify_whatsapp_text' => 'Hola!',
        ]);

        $wa = WapifyWhatsAppHelper::resolve();

        $this->assertStringContainsString('phone=34613194131', $wa['api_url']);
        $this->assertStringContainsString('text=Hola%21', $wa['api_url']);
        $this->assertStringContainsString('web.whatsapp.com', $wa['web_url']);
    }

    public function test_qr_data_uri_returns_png_data_uri(): void
    {
        $uri = WapifyWhatsAppHelper::qrDataUri('https://example.com/test');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertGreaterThan(100, strlen($uri));
    }
}
