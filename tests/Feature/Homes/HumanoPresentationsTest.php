<?php

namespace Tests\Feature\Homes;

use Tests\TestCase;

class HumanoPresentationsTest extends TestCase
{
    public function test_presentation_brand_logo_links_to_public_landing(): void
    {
        $html = file_get_contents(public_path('homes/humano/presentations/primeros-pasos.html'));

        $this->assertIsString($html);
        $this->assertStringContainsString(
            '<a href="/" class="brand" aria-label="Volver a la landing"><img class="brand-logo" src="/assets/logo-light.svg" alt="Humano"></a>',
            $html,
        );
    }

    public function test_facturacion_presentation_exists_and_has_expected_content(): void
    {
        $path = public_path('homes/humano/presentations/facturacion.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('del ticket al sistema contable', $html);
        $this->assertStringContainsString('organismos fiscales', $html);
        $this->assertStringContainsString('/presentacion/afiliados', $html);
    }

    public function test_afiliados_presentation_exists_and_has_expected_content(): void
    {
        $path = public_path('homes/humano/presentations/afiliados.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('programa de referidos', $html);
        $this->assertStringContainsString('Comisión por referido', $html);
        $this->assertStringContainsString('__AFFILIATE_COMMISSION_PERCENT__%', $html);
    }

    public function test_cms_wordpress_presentation_exists_and_has_expected_content(): void
    {
        $path = public_path('homes/humano/presentations/cms-wordpress.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('contenido con WordPress', $html);
        $this->assertStringContainsString('IDONEO Custom Fields', $html);
        $this->assertStringContainsString('/help/plugins', $html);
        $this->assertStringContainsString('icf_fields', $html);
        $this->assertStringContainsString('/cms/posts', $html);
    }

    public function test_lista_de_60_presentation_exists_and_has_expected_content(): void
    {
        $path = public_path('homes/humano/presentations/lista-de-60.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('seguimiento prioritario', $html);
        $this->assertStringContainsString('Próximo contacto', $html);
        $this->assertStringContainsString('Agregar a la lista', $html);
        $this->assertStringContainsString('facturacion.html', $html);
        $this->assertStringContainsString('prospeccion.html', $html);
    }

    public function test_embudos_presentation_exists_and_has_expected_content(): void
    {
        $path = public_path('homes/humano/presentations/embudos.html');

        $this->assertFileExists($path);

        $html = file_get_contents($path);

        $this->assertIsString($html);
        $this->assertStringContainsString('flujos conversacionales', $html);
        $this->assertStringContainsString('No es lo mismo un', $html);
        $this->assertStringContainsString('Automatización', $html);
        $this->assertStringContainsString('Editor visual', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringContainsString('Humano • Chat', $html);
        $this->assertStringContainsString('quisiera una cita', $html);
        $this->assertStringContainsString('Nela Adela Cabrera', $html);
        $this->assertStringContainsString('Lead', $html);
        $this->assertStringContainsString('Conversión', $html);
        $this->assertStringContainsString('Gracias, Nela. ¿Qué día y hora te viene bien?', $html);
        $this->assertStringNotContainsString('registré como Lead', $html);
        $this->assertStringContainsString('pres-demo-phone', $html);
        $this->assertStringContainsString('yes_no', $html);
        $this->assertStringContainsString('/funnel/list', $html);
        $this->assertStringContainsString('insight-diario.html', $html);
        $this->assertStringContainsString(
            '<a href="/" class="brand" aria-label="Volver a la landing"><img class="brand-logo" src="/assets/logo-light.svg" alt="Humano"></a>',
            $html,
        );
    }
}
