<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpApiCommunicationsDocumentationTest extends TestCase
{
    public function test_communications_api_help_page_renders_send_and_show_documentation(): void
    {
        $response = $this->get(route('help.api.communications'));

        $response->assertOk();
        $response->assertSee('Communications API Reference', false);
        $response->assertSee('/api/team/communications', false);
        $response->assertSee('Authorization: Bearer', false);
        $response->assertSee('"channel": "email"', false);
        $response->assertSee('"channel": "whatsapp"', false);
        $response->assertSee('"channel": "sms"', false);
        $response->assertSee('attachments[]=', false);
        $response->assertSee('-F "channel=whatsapp"', false);
        $response->assertDontSee('"attachments": []', false);
        $response->assertSee('"file_name": "factura.pdf"', false);
        $response->assertSee('/api/team/communications/1842', false);
        $response->assertSee('"status": "pending"', false);
        $response->assertSee('"status": "sent"', false);
    }
}
