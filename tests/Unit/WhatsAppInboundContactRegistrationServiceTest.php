<?php

namespace Tests\Unit;

use App\Services\WhatsApp\WhatsAppInboundContactRegistrationService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsAppInboundContactRegistrationServiceTest extends TestCase
{
    public function test_validate_full_name_rejects_greeting_phrase(): void
    {
        Config::set('ai.providers.anthropic.key', null);
        Config::set('ai.providers.openai.key', null);

        $service = app(WhatsAppInboundContactRegistrationService::class);

        $result = $service->validateFullName('Qué tal');

        $this->assertFalse($result['valid']);
    }

    public function test_validate_full_name_rejects_single_name(): void
    {
        Config::set('ai.providers.anthropic.key', null);
        Config::set('ai.providers.openai.key', null);

        $service = app(WhatsAppInboundContactRegistrationService::class);

        $result = $service->validateFullName('Pepe');

        $this->assertFalse($result['valid']);
    }

    public function test_validate_full_name_accepts_first_and_last_name(): void
    {
        Config::set('ai.providers.anthropic.key', null);
        Config::set('ai.providers.openai.key', null);

        $service = app(WhatsAppInboundContactRegistrationService::class);

        $result = $service->validateFullName('Pepe Suárez');

        $this->assertTrue($result['valid']);
        $this->assertSame('Pepe', $result['first_name']);
        $this->assertSame('Suárez', $result['last_name']);
    }
}
