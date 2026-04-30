<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignsIndexTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_campaigns_datatables_ajax_returns_json_when_accept_json(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Ajax Test Campaign',
        ]);

        $columnDefs = [
            ['campaign_display', false],
            ['performance_display', false],
            ['status', false],
            ['action', false],
        ];
        $columns = [];
        foreach ($columnDefs as [$data, $orderable])
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'false',
                'orderable' => $orderable ? 'true' : 'false',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('campaigns.index'), [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'columns' => $columns,
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type'),
        );
        $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        $this->assertGreaterThanOrEqual(1, (int) $response->json('recordsFiltered'));
    }

    public function test_campaigns_page_shows_campaign_manager_sections_when_authenticated(): void
    {
        $user = $this->userWithPersonalTeamResolved();

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
            str_contains($html, 'Plantillas'),
        );
        $this->assertTrue(
            str_contains($html, 'Nueva campaña'),
        );
        $this->assertTrue(
            str_contains($html, 'campaigns-table'),
        );
    }

    public function test_campaign_edit_page_shows_sequence_settings_sections(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->sequenceSummary()->create([
            'team_id' => $user->current_team_id,
        ]);

        $response = $this->actingAs($user)->get(route('campaigns.edit', ['campaign' => $campaign]));

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
            str_contains($html, 'Colección Humano'),
        );
        $this->assertTrue(
            str_contains($html, 'Plantillas destacadas'),
        );
    }

    public function test_classic_editor_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.classic-editor', [
            'type' => 'sequences',
            'title' => 'Mi secuencia',
            'template_id' => 2,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Nuevo correo de secuencia'),
        );
        $this->assertTrue(
            str_contains($html, 'Título interno'),
        );
        $this->assertTrue(
            str_contains($html, 'Asunto'),
        );
    }

    public function test_grapes_editor_page_is_reachable_when_authenticated(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get(route('campaigns.classic-editor.grapes', [
            'type' => 'sequences',
            'title' => 'Mi secuencia',
            'template_id' => 2,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertTrue(
            str_contains($html, 'Editor visual de correo'),
        );
        $this->assertTrue(
            str_contains($html, 'gjs-editor'),
        );
    }
}
