<?php

namespace Tests\Unit;

use App\Models\Contact;
use Tests\TestCase;

class ContactShortcutUrlsTest extends TestCase
{
    public function test_mail_compose_list_url_requires_valid_email(): void
    {
        $contact = new Contact(['email' => 'invalid']);

        $this->assertNull($contact->mailComposeListUrl());
    }

    public function test_mail_compose_list_url_is_non_null_for_valid_email(): void
    {
        $contact = new Contact([
            'name' => 'Pat',
            'surname' => 'Lee',
            'email' => 'pat@example.com',
        ]);

        $url = $contact->mailComposeListUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString(rawurlencode('pat@example.com'), $url);
        $this->assertStringContainsString('compose=1', $url);
    }

    public function test_chat_index_url_uses_phone_when_present(): void
    {
        $contact = new Contact(['phone' => '5491112345678']);

        $url = $contact->chatIndexUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('phone=', $url);
    }
}
