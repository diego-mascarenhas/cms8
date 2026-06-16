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
        $this->assertStringContainsString('Comisión configurable', $html);
    }
}
