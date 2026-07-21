<?php

namespace Tests\Feature;

use App\Enums\AutomationReplyType;
use App\Models\Automation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\AutomationFlowGraphSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomationFlowGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_reload_flow_graph(): void
    {
        $user = $this->createAdminWithAutomationsModule();
        $automation = Automation::factory()->funnel()->create([
            'team_id' => $user->current_team_id,
            'channels' => Automation::normalizeChannels(['api' => true, 'chat' => true]),
        ]);

        $payload = [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => 'Ask what they need',
                    'is_entry' => true,
                    'position_x' => 10,
                    'position_y' => 20,
                    'outputs' => [
                        ['id' => 'output_1', 'reply_type' => 'yes_no', 'match_value' => 'yes', 'label' => 'Sí'],
                        ['id' => 'output_2', 'reply_type' => 'fallback', 'match_value' => null, 'label' => 'Otra'],
                    ],
                ],
                [
                    'client_id' => '2',
                    'label' => 'Confirmado',
                    'instruction' => 'Great',
                    'is_entry' => false,
                    'position_x' => 300,
                    'position_y' => 20,
                    'outputs' => [],
                ],
            ],
            'edges' => [
                [
                    'from_client_id' => '1',
                    'from_output' => 'output_1',
                    'to_client_id' => '2',
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson(route('funnel.flow.save', $automation), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $automation->refresh()->load('steps.transitions');
        $this->assertCount(2, $automation->steps);
        $entry = $automation->steps->firstWhere('is_entry', true);
        $this->assertNotNull($entry);
        $this->assertCount(1, $entry->transitions);
        $this->assertSame(AutomationReplyType::YesNo, $entry->transitions->first()->reply_type);

        $exported = app(AutomationFlowGraphSyncer::class)->export($automation);
        $this->assertCount(2, $exported['nodes']);
        $this->assertNotEmpty($exported['edges']);
    }

    private function createAdminWithAutomationsModule(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        Module::firstOrCreate(
            ['key' => 'automations'],
            ['name' => 'Automations', 'description' => 'Test', 'is_core' => 0, 'status' => 1, 'order' => 0, 'group' => 'automation'],
        );
        $team->enableModule('automations');

        return $user->refresh();
    }
}
