<?php

namespace Tests\Feature\Homes;

use App\Support\GuidePresentation;
use Tests\TestCase;

class GuidePresentationTest extends TestCase
{
    public function test_presentacion_routes_are_publicly_accessible(): void
    {
        foreach (GuidePresentation::SLUGS as $slug)
        {
            $this->get('/presentacion/'.$slug)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    public function test_afiliados_presentation_injects_base_tag_for_assets(): void
    {
        $this->get('/presentacion/afiliados')
            ->assertOk()
            ->assertSee('<base href="/homes/humano/presentations/">', false)
            ->assertSee('programa de referidos', false)
            ->assertSee('Activa Stripe', false)
            ->assertSee('Comisión por referido', false)
            ->assertSee('/presentacion/cms-wordpress', false);
    }

    public function test_cms_wordpress_presentation_injects_base_tag_for_assets(): void
    {
        $this->get('/presentacion/cms-wordpress')
            ->assertOk()
            ->assertSee('<base href="/homes/humano/presentations/">', false)
            ->assertSee('contenido con WordPress', false)
            ->assertSee('IDONEO CMS Sync', false)
            ->assertSee('Se actualizó la entrada', false);
    }

    public function test_calendario_presentation_injects_base_tag_for_assets(): void
    {
        $this->get('/presentacion/calendario')
            ->assertOk()
            ->assertSee('<base href="/homes/humano/presentations/">', false);
    }

    public function test_legacy_affiliates_url_redirects_to_presentacion_afiliados(): void
    {
        $this->get('/affiliates')
            ->assertRedirect('/presentacion/afiliados');
    }

    public function test_legacy_static_html_paths_redirect_to_presentacion_routes(): void
    {
        $this->get('/homes/humano/presentations/facturacion.html')
            ->assertRedirect('/presentacion/facturacion');

        $this->get('/humano-presentacion.html')
            ->assertRedirect('/presentacion/primeros-pasos');
    }

    public function test_unknown_presentacion_slug_returns_not_found(): void
    {
        $this->get('/presentacion/no-existe')->assertNotFound();
    }
}
