<?php

namespace Tests\Unit;

use App\Helpers\SupportWhatsAppHelper;
use Tests\TestCase;

class SupportWhatsAppHelperTest extends TestCase
{
    public function test_web_url_uses_whatsapp_support_number_when_configured(): void
    {
        config([
            'app.whatsapp_support' => '+34 722 37 28 58',
            'app.wapify_whatsapp_phone' => '34613194131',
            'app.wapify_whatsapp_text' => '',
        ]);

        $this->assertSame(
            'https://web.whatsapp.com/send?phone=34722372858',
            SupportWhatsAppHelper::webUrl(),
        );
    }

    public function test_web_url_falls_back_to_wapify_phone_when_support_number_missing(): void
    {
        config([
            'app.whatsapp_support' => '',
            'app.wapify_whatsapp_phone' => '34613194131',
            'app.wapify_whatsapp_text' => '',
        ]);

        $this->assertSame(
            'https://web.whatsapp.com/send?phone=34613194131',
            SupportWhatsAppHelper::webUrl(),
        );
    }

    public function test_web_url_includes_prefilled_text_from_config(): void
    {
        config([
            'app.whatsapp_support' => '34624159557',
            'app.wapify_whatsapp_text' => 'Hola!',
        ]);

        $this->assertSame(
            'https://web.whatsapp.com/send?phone=34624159557&text=Hola%21',
            SupportWhatsAppHelper::webUrl(),
        );
    }
}
