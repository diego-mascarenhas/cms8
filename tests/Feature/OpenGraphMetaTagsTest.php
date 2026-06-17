<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenGraphMetaTagsTest extends TestCase
{
    public function test_login_page_does_not_include_share_preview_image(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('property="og:image"', false)
            ->assertDontSee('name="twitter:image"', false)
            ->assertSee('name="twitter:card" content="summary"', false);
    }

    public function test_help_page_includes_share_preview_image(): void
    {
        $expectedOgImageUrl = url('/images/system-onboarding/whatsapp-image.jpg');

        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('property="og:image" content="'.$expectedOgImageUrl.'"', false)
            ->assertSee('name="twitter:image" content="'.$expectedOgImageUrl.'"', false)
            ->assertSee('property="og:image:width" content="552"', false)
            ->assertSee('property="og:image:height" content="552"', false);
    }

    public function test_manual_page_includes_share_preview_image(): void
    {
        $expectedOgImageUrl = url('/images/system-onboarding/whatsapp-image.jpg');

        $this->get(route('manual.index'))
            ->assertOk()
            ->assertSee('property="og:image" content="'.$expectedOgImageUrl.'"', false)
            ->assertSee('name="twitter:image" content="'.$expectedOgImageUrl.'"', false);
    }
}
