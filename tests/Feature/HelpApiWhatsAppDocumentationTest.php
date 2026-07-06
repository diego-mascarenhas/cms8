<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpApiWhatsAppDocumentationTest extends TestCase
{
    public function test_whatsapp_api_help_page_renders_send_endpoint_documentation(): void
    {
        $response = $this->get(route('help.api.whatsapp'));

        $response->assertOk();
        $response->assertSee('WhatsApp API Reference', false);
        $response->assertSee('/api/team/whatsapp/send', false);
        $response->assertSee('"to": "+34722372858"', false);
        $response->assertSee('Mensaje de prueba desde la API de Humano', false);
        $response->assertSee('"success": true', false);
    }
}
