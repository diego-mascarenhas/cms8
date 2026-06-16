<?php

namespace Tests\Feature\Homes;

use Tests\TestCase;

class AffiliatesGuidePageTest extends TestCase
{
    public function test_affiliates_guide_page_is_publicly_accessible(): void
    {
        $response = $this->get('/affiliates');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('<base href="/homes/humano/presentations/">', false);
        $response->assertSee('programa de referidos', false);
        $response->assertSee('Activá Stripe', false);
        $response->assertSee('Comisión configurable', false);
        $response->assertSee('facturacion.html', false);
    }
}
