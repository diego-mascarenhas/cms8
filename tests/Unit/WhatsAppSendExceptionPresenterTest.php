<?php

namespace Tests\Unit;

use App\Support\WhatsAppSendExceptionPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsAppSendExceptionPresenterTest extends TestCase
{
    #[DataProvider('connectionFailureMessagesProvider')]
    public function test_maps_connection_failures_to_local_unreachable_message(string $rawMessage): void
    {
        $e = new \RuntimeException($rawMessage);
        $out = WhatsAppSendExceptionPresenter::messageForUser($e);

        $this->assertSame(__('whatsapp.send.error.local_unreachable'), $out);
    }

    public static function connectionFailureMessagesProvider(): array
    {
        return [
            'curl 7' => ['cURL error 7: Failed to connect to localhost port 3000 for http://localhost:3000/send-message'],
            'could not connect' => ['Could not connect to server'],
            'connection refused' => ['Connection refused'],
        ];
    }

    public function test_maps_not_configured_message(): void
    {
        $e = new \RuntimeException('Local WhatsApp service is not configured (check WHATSAPP_LOCAL_BASE_URL).');
        $this->assertSame(__('whatsapp.send.error.local_not_configured'), WhatsAppSendExceptionPresenter::messageForUser($e));
    }

    public function test_preserves_twilio_63016_message(): void
    {
        $raw = 'Twilio error 63016 outside window';
        $e = new \RuntimeException($raw);
        $this->assertSame($raw, WhatsAppSendExceptionPresenter::messageForUser($e));
    }

    public function test_maps_not_connected_message(): void
    {
        $e = new \RuntimeException('Local WhatsApp send failed: {"error":"WhatsApp not connected. Scan QR first."}');
        $this->assertSame(__('whatsapp.send.error.not_connected'), WhatsAppSendExceptionPresenter::messageForUser($e));
    }

    public function test_maps_connected_but_not_ready_without_asking_to_scan_qr(): void
    {
        $e = new \RuntimeException('Local WhatsApp send failed: {"error":"WhatsApp not ready to send. Wait for connection or scan QR first."}');
        $this->assertSame(__('whatsapp.send.error.not_ready'), WhatsAppSendExceptionPresenter::messageForUser($e));
    }

    public function test_generic_fallback(): void
    {
        $e = new \RuntimeException('Some unknown upstream failure');
        $this->assertSame(__('whatsapp.send.error.generic'), WhatsAppSendExceptionPresenter::messageForUser($e));
    }
}
