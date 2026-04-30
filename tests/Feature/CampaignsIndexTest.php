<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_page_shows_campaign_manager_sections_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.index'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Campañas'),
        );
        $this->assertTrue(
            str_contains($html, 'Campañas de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'Gestionar plantillas'),
        );
        $this->assertTrue(
            str_contains($html, 'Nueva campaña de correo'),
        );
    }

    public function test_campaign_edit_page_shows_sequence_settings_sections(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.edit', ['campaign' => 'teacher-onboarding-flow']));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Configuración de secuencia de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'Detalles de la secuencia'),
        );
        $this->assertTrue(
            str_contains($html, 'Automatizaciones'),
        );
        $this->assertTrue(
            str_contains($html, 'Guardar'),
        );
    }

    public function test_template_selection_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.templates.select', [
            'type' => 'broadcasts',
            'title' => 'Mi campaña',
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Selecciona una plantilla'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas personalizadas guardadas'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas Kajabi'),
        );
    }
}
