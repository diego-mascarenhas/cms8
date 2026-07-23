<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_automation_list(): void
    {
        $user = $this->createAdminWithAutomationsModule();

        $this->actingAs($user)
            ->get(route('automation-list'))
            ->assertOk();
    }

    public function test_admin_can_create_automation(): void
    {
        $user = $this->createAdminWithAutomationsModule();

        $response = $this->actingAs($user)->post(route('automation.store'), [
            'kind' => 'action',
            'name' => 'Soporte web',
            'slug' => 'soporte-web',
            'entry_prompt_key' => 'contacts:landing',
            'is_active' => '1',
            'channels' => [
                'api' => '1',
                'chat' => '1',
            ],
            'settings' => [
                'welcome_message' => 'Hola',
            ],
        ]);

        $automation = Automation::withoutGlobalScope('team')->where('slug', 'soporte-web')->first();
        $this->assertNotNull($automation);
        $response->assertRedirect(route('automation.show', $automation));
        $this->assertTrue($automation->isAction());
        $this->assertTrue($automation->allowsChannel(Automation::CHANNEL_API));
        $this->assertTrue($automation->allowsChannel(Automation::CHANNEL_CHAT));
        $this->assertFalse($automation->allowsChannel(Automation::CHANNEL_WHATSAPP));
        $this->assertSame('contacts:landing', $automation->entry_prompt_key);
        $this->assertNotEmpty($automation->public_token);
    }

    public function test_admin_can_view_funnel_list_and_create_funnel(): void
    {
        $user = $this->createAdminWithAutomationsModule();

        $this->actingAs($user)
            ->get(route('funnel-list'))
            ->assertOk();

        $response = $this->actingAs($user)->post(route('automation.store'), [
            'kind' => 'funnel',
            'name' => 'Embudo demo',
            'slug' => 'embudo-demo',
            'is_active' => '1',
            'channels' => ['chat' => '1', 'api' => '1'],
        ]);

        $funnel = Automation::withoutGlobalScope('team')->where('slug', 'embudo-demo')->first();
        $this->assertNotNull($funnel);
        $this->assertTrue($funnel->isFunnel());
        $response->assertRedirect(route('funnel.flow', $funnel));
    }

    public function test_non_admin_cannot_create_automation(): void
    {
        $user = $this->createUserWithTeamAndRole('user');
        $this->enableAutomationsModule($user->currentTeam);

        $this->actingAs($user)
            ->post(route('automation.store'), [
                'kind' => 'action',
                'name' => 'Blocked',
                'slug' => 'blocked',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_and_regenerate_token(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $automation = Automation::factory()->create([
            'team_id' => $user->current_team_id,
            'slug' => 'old-slug',
            'public_token' => 'oldtokenoldtokenoldtokenoldtokenoldtokenoldtokenoldtokenoldtoken12',
        ]);
        $oldToken = $automation->public_token;

        $this->actingAs($user)->put(route('automation.update', $automation), [
            'name' => 'Updated',
            'slug' => 'old-slug',
            'is_active' => '1',
            'channels' => ['api' => '1'],
            'regenerate_token' => '1',
        ])->assertRedirect(route('automation.show', $automation));

        $automation->refresh();
        $this->assertSame('Updated', $automation->name);
        $this->assertNotSame($oldToken, $automation->public_token);
    }

    public function test_admin_can_deactivate_automation_via_unchecked_checkbox(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $automation = Automation::factory()->create([
            'team_id' => $user->current_team_id,
            'slug' => 'active-funnel',
            'is_active' => true,
        ]);

        // Unchecked HTML checkboxes omit the field entirely from the request.
        $this->actingAs($user)->put(route('automation.update', $automation), [
            'name' => $automation->name,
            'slug' => 'active-funnel',
            'channels' => ['whatsapp' => '1'],
        ])->assertRedirect(route('automation.show', $automation));

        $automation->refresh();
        $this->assertFalse($automation->is_active);
    }

    public function test_admin_can_view_funnel_overview_read_only(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Embudo lectura',
            'channels' => Automation::normalizeChannels(['chat' => true]),
        ]);

        app(\App\Services\AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Preguntá qué necesita',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'choice', 'match_value' => 'cita', 'label' => 'Cita'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '2',
                    'label' => 'Cierre',
                    'instruction' => 'Despedí',
                    'is_entry' => false,
                    'position_x' => 200,
                    'position_y' => 0,
                    'outputs' => [],
                ],
            ],
            'edges' => [
                ['from_client_id' => '1', 'from_output' => 'output_1', 'to_client_id' => '2'],
                ['from_client_id' => '1', 'from_output' => 'output_2', 'to_client_id' => '2'],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('funnel.show', $funnel))
            ->assertOk()
            ->assertSee('Embudo lectura')
            ->assertSee('Preguntá qué necesita')
            ->assertSee('Cita')
            ->assertSee(__('Si el usuario responde…'))
            ->assertDontSee('id="automation-drawflow"', false);

        $this->actingAs($user)
            ->get(route('automation.show', $funnel))
            ->assertNotFound();
    }

    public function test_funnel_show_rejects_action_automations(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $action = Automation::factory()->action()->create([
            'team_id' => $user->current_team_id,
        ]);

        $this->actingAs($user)
            ->get(route('funnel.show', $action))
            ->assertNotFound();
    }

    public function test_funnel_list_actions_do_not_include_delete(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Sin eliminar en lista',
        ]);

        $this->actingAs($user);
        $html = view('automation.action', ['automation' => $funnel])->render();

        $this->assertStringContainsString(route('funnel.show', $funnel), $html);
        $this->assertStringContainsString(route('funnel.flow', $funnel), $html);
        $this->assertStringNotContainsString('ti-trash', $html);
        $this->assertStringNotContainsString(__('Eliminar'), $html);
    }

    private function createAdminWithAutomationsModule(): User
    {
        $user = $this->createUserWithTeamAndRole('admin');
        $this->enableAutomationsModule($user->currentTeam);

        return $user->refresh();
    }

    private function enableAutomationsModule(Team $team): void
    {
        Module::firstOrCreate(
            ['key' => 'automations'],
            [
                'name' => 'Automations',
                'description' => 'Automations',
                'is_core' => 0,
                'status' => 1,
                'order' => 0,
                'group' => 'automation',
            ],
        );
        $team->enableModule('automations');
    }

    private function createUserWithTeamAndRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }
}
