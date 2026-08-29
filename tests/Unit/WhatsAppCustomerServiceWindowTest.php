<?php

namespace Tests\Unit;

use App\Exceptions\WhatsAppSessionWindowClosedException;
use App\Models\Conversation;
use App\Services\WhatsApp\WhatsAppCustomerServiceWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppCustomerServiceWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_when_customer_never_wrote(): void
    {
        $window = app(WhatsAppCustomerServiceWindow::class);

        $this->assertFalse($window->isOpen('5491112345678'));
        $this->expectException(WhatsAppSessionWindowClosedException::class);
        $window->assertOpen('5491112345678');
    }

    public function test_closed_when_last_inbound_is_older_than_24_hours(): void
    {
        $this->createInbound('5491112345678', now()->subHours(25));

        $this->assertFalse(app(WhatsAppCustomerServiceWindow::class)->isOpen('5491112345678'));
    }

    public function test_open_when_customer_wrote_inside_the_window(): void
    {
        $this->createInbound('5491112345678', now()->subHours(2));

        $this->assertTrue(app(WhatsAppCustomerServiceWindow::class)->isOpen('5491112345678'));
        app(WhatsAppCustomerServiceWindow::class)->assertOpen('5491112345678');
    }

    public function test_matches_spanish_mobile_with_and_without_country_code(): void
    {
        $this->createInbound('34600111222', now()->subMinutes(10));

        $this->assertTrue(app(WhatsAppCustomerServiceWindow::class)->isOpen('600111222'));
    }

    public function test_describe_reports_closed_window_and_last_inbound(): void
    {
        $at = now()->subHours(2);
        $this->createInbound('5491112345678', $at);

        $described = app(WhatsAppCustomerServiceWindow::class)->describe('5491112345678');

        $this->assertTrue($described['open']);
        $this->assertSame($at->toIso8601String(), $described['last_inbound_at']);
        $this->assertFalse(app(WhatsAppCustomerServiceWindow::class)->describe('54900000000')['open']);
    }

    public function test_can_be_disabled_from_config(): void
    {
        config()->set('whatsapp.customer_service_window.enabled', false);

        $this->assertTrue(app(WhatsAppCustomerServiceWindow::class)->isOpen('5491112345678'));
    }

    private function createInbound(string $from, \DateTimeInterface $at): void
    {
        $conversation = Conversation::create([
            'message_sid' => 'wa_window_'.uniqid(),
            'channel' => 'whatsapp',
            'from' => $from,
            'to' => '34900000000',
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);
        $conversation->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
