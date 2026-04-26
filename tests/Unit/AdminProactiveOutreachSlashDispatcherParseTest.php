<?php

namespace Tests\Unit;

use App\Services\AdminProactiveOutreachSlashDispatcher;
use Tests\TestCase;

class AdminProactiveOutreachSlashDispatcherParseTest extends TestCase
{
    public function test_parse_enviar_onboarding_with_phone(): void
    {
        $dispatcher = app(AdminProactiveOutreachSlashDispatcher::class);

        $parsed = $dispatcher->parseSlashBody('/enviar-onboarding +34600111222');

        $this->assertNotNull($parsed);
        $this->assertSame('onboarding', $parsed['keyword']);
        $this->assertSame('34600111222', $parsed['phone_digits']);
    }

    public function test_parse_send_onboarding_is_case_insensitive(): void
    {
        $dispatcher = app(AdminProactiveOutreachSlashDispatcher::class);

        $parsed = $dispatcher->parseSlashBody('/SEND-ONBOARDING +34 600 11 12 22');

        $this->assertNotNull($parsed);
        $this->assertSame('onboarding', $parsed['keyword']);
        $this->assertSame('34600111222', $parsed['phone_digits']);
    }

    public function test_is_proactive_outreach_slash_recognizes_onboarding(): void
    {
        $dispatcher = app(AdminProactiveOutreachSlashDispatcher::class);

        $this->assertTrue($dispatcher->isProactiveOutreachSlash('/enviar-onboarding +34'));
        $this->assertTrue($dispatcher->isProactiveOutreachSlash('/send-onboarding +34'));
    }
}
