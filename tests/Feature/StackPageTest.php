<?php

namespace Tests\Feature;

use Tests\TestCase;

class StackPageTest extends TestCase
{
    public function test_stack_page_is_publicly_accessible(): void
    {
        $response = $this->get('/stack');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Stack técnico Humano', false);
        $response->assertSee('PostgreSQL', false);
        $response->assertSee('compatibilidad', false);
        $response->assertSee('Hetzner', false);
        $response->assertSee('REVISION ALPHA', false);
        $response->assertSee('Node + Baileys', false);
        $response->assertDontSee('Node 18+', false);
        $response->assertSee('Flutter', false);
        $response->assertSee('App Store', false);
        $response->assertSee('Play Store', false);
        $response->assertSee('Afiliados', false);
        $response->assertSee('Mercado Pago', false);
        $response->assertSee('ARCA', false);
        $response->assertSee('GitGuardian', false);
        $response->assertSee('staging', false);
        $response->assertSee('gitguardian.com', false);
        $response->assertSee('Backblaze', false);
        $response->assertSee('backblaze.com', false);
        $response->assertSee('OVH / Hetzner · REVISION ALPHA', false);
        $response->assertDontSee('OVH / Hetzner / WHM', false);
    }
}
