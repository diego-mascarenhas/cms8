<?php

namespace Tests\Feature;

use App\Support\ManualDocumentation;
use Tests\TestCase;

class ManualAndMockupsTest extends TestCase
{
    public function test_manual_index_is_public(): void
    {
        $this->get(route('manual.index'))
            ->assertOk()
            ->assertSee('Manual de usuario', false)
            ->assertSee(route('mockups.index', [], false), false);
    }

    public function test_manual_sections_are_public(): void
    {
        foreach (array_column(\App\Http\Controllers\ManualController::guideSections(), 'route') as $routeName)
        {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_manual_pages_include_admin_and_collaborator_role_blocks(): void
    {
        $this->get(route('manual.contacts'))
            ->assertOk()
            ->assertSee('Admin', false)
            ->assertSee('Collaborator', false)
            ->assertSee('Client', false)
            ->assertSee(route('mockups.contact-form', [], false), false);

        $this->get(route('manual.billing'))
            ->assertOk()
            ->assertSee('solo Admin', false)
            ->assertSee(route('mockups.invoice-flow', [], false), false);
    }

    public function test_client_mockups_and_flow_diagrams_are_public(): void
    {
        $this->get(route('mockups.overview'))
            ->assertOk()
            ->assertSee('¿Qué rol tiene?', false);

        $this->get(route('mockups.roles-flow'))
            ->assertOk()
            ->assertSee('Cliente final', false);

        $this->get(route('mockups.client-journey'))
            ->assertOk()
            ->assertSee('Client', false);

        $this->get(route('mockups.client-ticket'))
            ->assertOk()
            ->assertSee('Asunto', false);

        $this->get(route('mockups.client-home'))
            ->assertOk()
            ->assertSee('Mis proyectos', false);
    }

    public function test_mockups_catalog_and_pages_are_public(): void
    {
        $this->get(route('mockups.index'))
            ->assertOk()
            ->assertSee('Mockups', false);

        foreach (ManualDocumentation::mockups() as $mockup)
        {
            $this->get(route($mockup['route']))
                ->assertOk()
                ->assertSee($mockup['title'], false);
        }
    }

    public function test_role_matrix_covers_all_manual_sections(): void
    {
        $matrix = ManualDocumentation::roleMatrix();

        $this->assertArrayHasKey('contacts', $matrix);
        $this->assertArrayHasKey('billing', $matrix);
        $this->assertArrayHasKey('client', $matrix['getting-started']);
        $this->assertNotEmpty($matrix['billing']['collaborator_blocked']);
        $this->assertNotEmpty($matrix['contacts']['admin']);
        $this->assertNotEmpty($matrix['contacts']['collaborator']);
        $this->assertNotEmpty($matrix['contacts']['client']);
    }
}
