<?php

namespace Tests\Unit;

use App\Models\Contact;
use Tests\TestCase;

class ContactAllowsInboundChatAssistantTest extends TestCase
{
    public function test_allows_inbound_chat_assistant_when_data_flag_missing(): void
    {
        $contact = new Contact;
        $contact->data = (object) ['other' => 'x'];

        $this->assertTrue($contact->allowsInboundChatAssistant());
    }

    public function test_allows_inbound_chat_assistant_when_flag_true(): void
    {
        $contact = new Contact;
        $contact->data = (object) ['chat_assistant_ai_enabled' => true];

        $this->assertTrue($contact->allowsInboundChatAssistant());
    }

    public function test_disallows_inbound_chat_assistant_when_flag_false(): void
    {
        $contact = new Contact;
        $contact->data = (object) ['chat_assistant_ai_enabled' => false];

        $this->assertFalse($contact->allowsInboundChatAssistant());
    }
}
