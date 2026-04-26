<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpChatAssistantDocumentationTest extends TestCase
{
    public function test_chat_assistant_help_page_renders_config_help_hints_section(): void
    {
        $webHint = trim((string) config('humano_interactive_guide.web_help_hint', ''));

        $this->assertNotSame('', $webHint);

        $response = $this->get(route('help.chat-assistant'));

        $response->assertStatus(200);
        $response->assertSee('id="assistant-help-hints"', false);
        $response->assertSee(e($webHint), false);
    }
}
