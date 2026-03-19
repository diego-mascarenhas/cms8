<?php

namespace Tests\Feature;

use Tests\TestCase;

class WapifyLandingTest extends TestCase
{
    public function test_wapify_page_includes_idoneo_footer_logo(): void
    {
        $response = $this->get(route('wapify'));

        $response->assertStatus(200);
        $response->assertSee('wapify-footer', false);
        $response->assertSee('idoneo.svg', false);
        $response->assertSee('wapify-footer-brand', false);
        $response->assertSee('https://www.idoneo.dev', false);
        $response->assertSee('Escaneá el código QR', false);
    }
}
