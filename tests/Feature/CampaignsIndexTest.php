<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_campaigns_edit_literal_path_is_not_matched_as_numeric_id(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get('/campaigns/edit');

        $response->assertNotFound();
    }

    public function test_campaign_update_persists_name_and_redirects_to_show(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $campaign = Campaign::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Nombre inicial',
        ]);

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Título actualizado',
            'send_time_zone' => 'Europe/Madrid',
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHas('success');
        $campaign->refresh();
        $this->assertSame('Título actualizado', $campaign->name);
        $this->assertSame('Europe/Madrid', data_get($campaign->settings, 'send_time_zone'));
        $this->assertSame([], data_get($campaign->settings, 'automations', []));
    }

    public function test_campaign_update_persists_sequence_pivot_and_automations(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $teamId = (int) $user->current_team_id;

        DB::table('message_type')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Mailer', 'status' => 1],
        );
        DB::table('message_type')->updateOrInsert(
            ['id' => 2],
            ['name' => 'WhatsApp', 'status' => 1],
        );

        $messageA = Message::withoutGlobalScopes()->create([
            'name' => 'Paso A',
            'type_id' => 1,
            'text' => 'A',
            'team_id' => $teamId,
        ]);
        $messageB = Message::withoutGlobalScopes()->create([
            'name' => 'Paso B',
            'type_id' => 2,
            'text' => 'B',
            'team_id' => $teamId,
        ]);

        $campaign = Campaign::factory()->create([
            'team_id' => $teamId,
            'name' => 'Secuencia test',
        ]);

        $campaign->messages()->syncWithoutDetaching([
            $messageA->id => ['sort_order' => 0, 'delay_minutes_after_previous' => null, 'conditions' => null],
            $messageB->id => ['sort_order' => 1, 'delay_minutes_after_previous' => null, 'conditions' => null],
        ]);

        $response = $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Secuencia test',
            'send_time_zone' => 'America/New_York',
            'sequence' => [
                [
                    'message_id' => $messageA->id,
                    'sort_order' => 1,
                    'delay_minutes_after_previous' => 60,
                    'condition_preset' => 'none',
                ],
                [
                    'message_id' => $messageB->id,
                    'sort_order' => 2,
                    'delay_minutes_after_previous' => '',
                    'condition_preset' => 'opened',
                ],
            ],
            'automations' => [
                [
                    'trigger' => 'if_opened_previous',
                    'delay_hours' => 24,
                    'channel_type_id' => 2,
                    'message_id' => $messageB->id,
                    'notes' => 'WA follow-up',
                ],
            ],
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $response->assertSessionHasNoErrors();

        $campaign->refresh();
        $this->assertSame('America/New_York', data_get($campaign->settings, 'send_time_zone'));
        $autos = data_get($campaign->settings, 'automations');
        $this->assertIsArray($autos);
        $this->assertCount(1, $autos);
        $this->assertSame('if_opened_previous', $autos[0]['trigger']);
        $this->assertSame(2, $autos[0]['channel_type_id']);
        $this->assertSame(24, $autos[0]['delay_hours']);
        $this->assertSame($messageB->id, $autos[0]['message_id']);

        $rowB = DB::table('campaign_message')
            ->where('campaign_id', $campaign->id)
            ->where('message_id', $messageB->id)
            ->first();
        $this->assertNotNull($rowB);
        $this->assertSame(2, (int) $rowB->sort_order);
        $this->assertSame('{"require_previous":"opened"}', $rowB->conditions);
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
            str_contains($html, 'Orden y condiciones de envío'),
        );
        $this->assertTrue(
            str_contains($html, 'Agregar automatización'),
        );
        $this->assertTrue(
            str_contains($html, 'Guardar'),
        );
        $this->assertTrue(
            str_contains($html, 'Volver'),
        );
        $this->assertTrue(
            str_contains($html, route('campaigns.index')),
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
