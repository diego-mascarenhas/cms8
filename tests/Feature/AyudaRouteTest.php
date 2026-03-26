<?php

namespace Tests\Feature;

use Tests\TestCase;

class AyudaRouteTest extends TestCase
{
    public function test_wapify_ayuda_page_is_public_and_ok(): void
    {
        $response = $this->get(route('wapify.ayuda'));

        $response->assertOk();
        $response->assertSee('Guía rápida', false);
    }

    public function test_legacy_ayuda_url_redirects_to_wapify_ayuda(): void
    {
        $this->get('/ayuda')->assertRedirect(route('wapify.ayuda'));
    }
}
