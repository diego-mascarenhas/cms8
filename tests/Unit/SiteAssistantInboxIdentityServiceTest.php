<?php

namespace Tests\Unit;

use App\Services\SiteAssistantInboxIdentityService;
use Tests\TestCase;

class SiteAssistantInboxIdentityServiceTest extends TestCase
{
    public function test_extracts_email_phone_and_name(): void
    {
        $hints = app(SiteAssistantInboxIdentityService::class)->extractHints([
            'Hola, soy Ana Pérez. ana.perez@example.com +34 600 111 222',
        ]);

        $this->assertSame('ana.perez@example.com', $hints['email']);
        $this->assertSame('34600111222', $hints['phone']);
        $this->assertSame('Hola soy Ana Pérez', $hints['name']);
    }

    public function test_identify_slash_becomes_the_ask_message(): void
    {
        $service = app(SiteAssistantInboxIdentityService::class);

        $this->assertTrue($service->isIdentifySlash('/identificar'));
        $this->assertSame($service->askMessage(), $service->staffReplyBody('/identificar'));
        $this->assertSame('Seguimos acá', $service->staffReplyBody('Seguimos acá'));
    }

    public function test_client_claim_is_detected(): void
    {
        $service = app(SiteAssistantInboxIdentityService::class);

        $this->assertTrue($service->isClientClaim('Ya soy cliente'));
        $this->assertTrue($service->isClientClaim('ya soy cliente.'));
        $this->assertFalse($service->isClientClaim('Ya soy cliente de siempre, ¿me ayudan?'));
    }
}
