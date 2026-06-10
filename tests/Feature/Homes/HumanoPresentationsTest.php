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
}
