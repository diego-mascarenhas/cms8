<?php

namespace Tests\Feature;

use App\Http\Controllers\ManualController;
use App\Support\ManualDocumentation;
use Tests\TestCase;

class ManualAndMockupsTest extends TestCase
{
    public function test_manual_index_is_public(): void
    {
        $this->get(route('manual.index'))
            ->assertOk()
            ->assertSee('Manual de usuario', false)
            ->assertSee(route('mockups.index', [], false), false)
            ->assertSee('Client', false);
    }

    public function test_manual_sections_are_public(): void
    {
        foreach (array_column(ManualController::guideSections(), 'route') as $routeName)
        {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_new_manual_sections_exist(): void
    {
        foreach (['manual.opportunities', 'manual.tickets', 'manual.automation', 'manual.website'] as $routeName)
        {
            $this->get(route($routeName))->assertOk();
        }

        $this->get(route('manual.tickets'))
            ->assertSee(route('mockups.client-ticket', [], false), false);

        $this->get(route('manual.billing'))
            ->assertSee('Suscripciones', false)
            ->assertSee('Afiliados', false);

        $this->get(route('manual.campaigns'))
            ->assertSee('Paid Ads', false);
    }

    public function test_manual_pages_include_role_blocks(): void
    {
        $this->get(route('manual.contacts'))
            ->assertOk()
            ->assertSee('Admin', false)
            ->assertSee('Collaborator', false)
            ->assertSee('Client', false);

        $this->get(route('manual.opportunities'))
            ->assertOk()
            ->assertSee('pipeline', false);
    }

    public function test_client_mockups_and_flow_diagrams_are_public(): void
    {
        $this->get(route('mockups.overview'))
            ->assertOk()
            ->assertSee('¿Qué rol tiene?', false);

        $this->get(route('mockups.client-journey'))
            ->assertOk()
            ->assertSee('Client', false);
    }

    public function test_mockups_catalog_and_pages_are_public(): void
    {
        $this->get(route('mockups.index'))->assertOk();

        foreach (ManualDocumentation::mockups() as $mockup)
        {
            $this->get(route($mockup['route']))
                ->assertOk()
                ->assertSee($mockup['title'], false);
        }
    }

    public function test_role_matrix_covers_new_sections(): void
    {
        $matrix = ManualDocumentation::roleMatrix();

        foreach (['opportunities', 'tickets', 'automation', 'website', 'contacts', 'billing'] as $section)
        {
            $this->assertArrayHasKey($section, $matrix);
            $this->assertArrayHasKey('admin', $matrix[$section]);
            $this->assertArrayHasKey('collaborator', $matrix[$section]);
            $this->assertArrayHasKey('client', $matrix[$section]);
        }
    }
}
