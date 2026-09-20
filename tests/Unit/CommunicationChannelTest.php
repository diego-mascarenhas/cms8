<?php

namespace Tests\Unit;

use App\Enums\CommunicationChannel;
use PHPUnit\Framework\TestCase;

class CommunicationChannelTest extends TestCase
{
    public function test_email_and_whatsapp_allow_attachments(): void
    {
        $this->assertTrue(CommunicationChannel::Email->allowsAttachments());
        $this->assertTrue(CommunicationChannel::WhatsApp->allowsAttachments());
        $this->assertFalse(CommunicationChannel::Sms->allowsAttachments());
    }
}
