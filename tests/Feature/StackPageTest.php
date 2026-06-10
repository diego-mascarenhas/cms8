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
        $response->assertSee('Facturación fiscal', false);
        $response->assertSee('GitGuardian', false);
        $response->assertSee('staging', false);
        $response->assertSee('gitguardian.com', false);
        $response->assertSee('Backblaze', false);
        $response->assertSee('backblaze.com', false);
        $response->assertSee('OVH / Hetzner · REVISION ALPHA', false);
        $response->assertDontSee('OVH / Hetzner / WHM', false);
    }

    public function test_stack_slide_page_is_publicly_accessible(): void
    {
        $response = $this->get('/stack/slide');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Humano — Stack técnico', false);
        $response->assertSee('SaaS multi-equipo · Laravel 12 · Livewire · APIs', false);
        $response->assertSee('PHP 8.4+', false);
        $response->assertSee('Backblaze', false);
        $response->assertSee('REVISION ALPHA', false);
        $response->assertSee('Prospection', false);
        $response->assertSee('APIs', false);
        $response->assertSee('ElevenLabs', false);
        $response->assertSee('Stripe · Mercado Pago', false);
        $response->assertSee('VeriFactu · ARCA', false);
        $response->assertSee('AEAT (España), Hacienda (Argentina)', false);
    }
}
