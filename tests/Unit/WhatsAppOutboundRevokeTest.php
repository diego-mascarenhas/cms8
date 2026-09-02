<?php

namespace Tests\Unit;

use App\Models\Conversation;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WhatsAppOutboundRevokeTest extends TestCase
{
    public function test_real_whatsapp_ids_can_be_revoked_for_two_days(): void
    {
        Carbon::setTestNow('2026-09-02 16:50:00');

        $message = new Conversation([
            'message_sid' => '3EB0DELETE01',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
        $message->created_at = Carbon::parse('2026-09-02 16:00:00');

        $this->assertSame('3EB0DELETE01', $message->whatsAppRemoteId());
        $this->assertTrue($message->canRevokeOnWhatsApp());

        Carbon::setTestNow('2026-09-04 16:00:01');
        $this->assertFalse($message->canRevokeOnWhatsApp());
        Carbon::setTestNow();
    }

    public function test_synthetic_attachment_ids_cannot_be_revoked(): void
    {
        $message = new Conversation([
            'message_sid' => 'wa_attach_abc',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
        $message->created_at = now();

        $this->assertNull($message->whatsAppRemoteId());
        $this->assertFalse($message->canRevokeOnWhatsApp());
    }
}
