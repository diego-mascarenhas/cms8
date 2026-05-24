<?php

namespace Tests\Unit;

use App\Support\AuthIntendedUrlGuard;
use Tests\TestCase;

class AuthIntendedUrlGuardTest extends TestCase
{
    public function test_sanitize_intended_url_replaces_whatsapp_qr_image_with_default(): void
    {
        $url = 'https://staging.humano.app/chat/whatsapp-qr-image?t=1779638437827';

        $this->assertSame('/', AuthIntendedUrlGuard::sanitizeIntendedUrl($url, '/'));
    }

    public function test_sanitize_intended_url_keeps_normal_pages(): void
    {
        $url = 'https://staging.humano.app/dashboard';

        $this->assertSame($url, AuthIntendedUrlGuard::sanitizeIntendedUrl($url, '/'));
    }

    public function test_sanitize_intended_url_replaces_onboarding_chat_link_qr_with_default(): void
    {
        $url = route('registration.onboarding.chat-link-qr-image').'?t=123';

        $this->assertSame('/', AuthIntendedUrlGuard::sanitizeIntendedUrl($url, '/'));
    }
}
