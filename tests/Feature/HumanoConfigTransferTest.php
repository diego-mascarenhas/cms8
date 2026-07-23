<?php

namespace Tests\Feature;

use App\Enums\AutomationReplyType;
use App\Models\Automation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AutomationFlowGraphSyncer;
use App\Services\HumanoConfigExporter;
use App\Services\HumanoConfigImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HumanoConfigTransferTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithModules(array $moduleKeys = ['automations', 'prompts']): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach ($moduleKeys as $key)
        {
            $module = Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
            $team->enableModule($module->key);
        }

        Module::query()->firstOrCreate(
            ['key' => 'calendar'],
            [
                'name' => 'Calendar',
                'icon' => 'calendar',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        return $user->refresh();
    }

    public function test_export_funnel_includes_belongs_to_header_and_portable_exit_slug(): void
    {
        $user = $this->adminWithModules();
        $teamId = (int) $user->current_team_id;

        $action = Automation::factory()->action()->create([
            'team_id' => $teamId,
            'name' => 'Enviar resumen',
            'slug' => 'enviar-resumen-por-email',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $teamId,
            'name' => 'Embudo demo',
            'slug' => 'embudo-demo',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Inicio',
                    'instruction' => '¿Email?',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'email',
                            'label' => 'Email',
                            'to_automation_id' => $action->id,
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'from_client_id' => '1',
                    'from_output' => 'output_1',
                    'to_client_id' => null,
                    'to_automation_id' => $action->id,
                ],
            ],
        ]);

        $this->actingAs($user);

        $document = app(HumanoConfigExporter::class)->exportFunnel($funnel->fresh());

        $this->assertSame('funnel', $document['humano_export']['type']);
        $this->assertSame('Embudo', $document['humano_export']['belongs_to']);
        $this->assertSame('embudo-demo', $document['humano_export']['source']['slug']);
        $this->assertSame(
            'enviar-resumen-por-email',
            $document['payload']['graph']['nodes'][0]['outputs'][0]['to_automation_slug'],
        );
        $this->assertArrayNotHasKey('to_automation_id', $document['payload']['graph']['nodes'][0]['outputs'][0]);
        $this->assertCount(1, $document['payload']['actions']);

        $response = $this->get(route('funnel.export', $funnel));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_import_funnel_round_trip_remaps_exit_actions(): void
    {
        $user = $this->adminWithModules();
        $teamId = (int) $user->current_team_id;
        $this->actingAs($user);

        $action = Automation::factory()->action()->create([
            'team_id' => $teamId,
            'slug' => 'agendar-cita',
            'entry_prompt_key' => 'calendar:assistant_citas',
            'channels' => Automation::normalizeChannels(['api' => true, 'humano' => true]),
        ]);
        $funnel = Automation::factory()->funnel()->create([
            'team_id' => $teamId,
            'slug' => 'embudo-citas',
            'channels' => Automation::normalizeChannels(['api' => true, 'humano' => true]),
        ]);

        app(AutomationFlowGraphSyncer::class)->sync($funnel, [
            'nodes' => [
                [
                    'client_id' => '1',
                    'label' => 'Ask',
                    'instruction' => 'Cita?',
                    'is_entry' => true,
                    'position_x' => 0,
                    'position_y' => 0,
                    'outputs' => [
                        [
                            'id' => 'output_1',
                            'reply_type' => AutomationReplyType::Choice->value,
                            'match_value' => 'cita',
                            'label' => 'Cita',
                            'to_automation_id' => $action->id,
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'from_client_id' => '1',
                    'from_output' => 'output_1',
                    'to_client_id' => null,
                    'to_automation_id' => $action->id,
                ],
            ],
        ]);

        $document = app(HumanoConfigExporter::class)->exportFunnel($funnel->fresh());

        $result = app(HumanoConfigImporter::class)->import($document, $user->currentTeam);
        $imported = $result['model'];

        $this->assertSame('funnel', $result['type']);
        $this->assertTrue($imported->isFunnel());
        $this->assertSame('embudo-citas-2', $imported->slug);

        $imported->load('steps.transitions');
        $transition = $imported->steps->first()?->transitions->first();
        $this->assertNotNull($transition);
        $this->assertSame($action->id, $transition->to_automation_id);
    }

    public function test_import_prompt_via_http_upserts_by_module_and_section(): void
    {
        $user = $this->adminWithModules();
        $module = Module::query()->where('key', 'calendar')->firstOrFail();

        Prompt::query()->create([
            'team_id' => $user->current_team_id,
            'module_id' => $module->id,
            'section_key' => 'assistant_citas',
            'section_label' => 'Citas',
            'prompt_instruction' => 'Old instruction',
            'is_active' => true,
            'order' => 1,
        ]);

        $document = [
            'humano_export' => [
                'type' => 'prompt',
                'label' => 'Prompt',
                'belongs_to' => 'Prompt',
                'version' => 1,
                'exported_at' => now()->toIso8601String(),
                'source' => ['name' => 'Citas', 'routing_key' => 'calendar:assistant_citas'],
            ],
            'payload' => [
                'prompt' => [
                    'module_key' => 'calendar',
                    'section_key' => 'assistant_citas',
                    'section_label' => 'Citas IA',
                    'prompt_instruction' => 'New instruction',
                    'helper_text' => null,
                    'is_active' => true,
                    'order' => 2,
                ],
            ],
        ];

        $this->actingAs($user)
            ->post(route('humano.import.store'), [
                'json' => json_encode($document),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('module_prompts', 1);
        $this->assertDatabaseHas('module_prompts', [
            'team_id' => $user->current_team_id,
            'module_id' => $module->id,
            'section_key' => 'assistant_citas',
            'prompt_instruction' => 'New instruction',
            'section_label' => 'Citas IA',
        ]);
    }

    public function test_export_action_and_prompt_routes(): void
    {
        $user = $this->adminWithModules();
        $module = Module::query()->where('key', 'calendar')->firstOrFail();
        $action = Automation::factory()->action()->create([
            'team_id' => $user->current_team_id,
            'slug' => 'ticket-soporte',
        ]);
        $prompt = Prompt::query()->create([
            'team_id' => $user->current_team_id,
            'module_id' => $module->id,
            'section_key' => 'landing',
            'section_label' => 'Landing',
            'prompt_instruction' => 'Help the user',
            'is_active' => true,
            'order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('automation.export', $action))
            ->assertOk()
            ->assertJsonPath('humano_export.type', 'action')
            ->assertJsonPath('humano_export.belongs_to', 'Automatización');

        $this->actingAs($user)
            ->get(route('prompt.export', $prompt))
            ->assertOk()
            ->assertJsonPath('humano_export.type', 'prompt')
            ->assertJsonPath('humano_export.belongs_to', 'Prompt');
    }
}
