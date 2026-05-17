<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenGraphMetaTagsTest extends TestCase
{
    public function test_login_page_uses_whatsapp_og_image(): void
    {
        $expectedOgImageUrl = url('/images/system-onboarding/whatsapp-image.jpg');

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('property="og:image" content="'.$expectedOgImageUrl.'"', false);
        $response->assertSee('name="twitter:image" content="'.$expectedOgImageUrl.'"', false);
        $response->assertSee('property="og:image:width" content="552"', false);
        $response->assertSee('property="og:image:height" content="552"', false);
    }
}
