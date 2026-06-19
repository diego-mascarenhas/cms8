<?php

namespace Tests\Feature;

use Tests\TestCase;

class PerformanceInsightLandingTest extends TestCase
{
    public function test_landing_page_is_public(): void
    {
        $this->get(route('performance-insight.landing'))->assertOk();
    }

    public function test_newsletter_preview_is_public(): void
    {
        $this->get(route('performance-insight.newsletter'))->assertOk();
    }

    public function test_guide_presentation_is_public(): void
    {
        $this->get(route('presentacion.show', 'insight-diario'))->assertOk();
    }
}
