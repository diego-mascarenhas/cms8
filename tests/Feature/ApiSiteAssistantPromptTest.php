<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiSiteAssistantPromptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function assistantUserWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team, $user->createToken('assistant-site-prompt-test')->plainTextToken];
    }

    public function test_site_prompt_requires_authentication(): void
    {
        $this->getJson('/api/assistant/site-prompt')->assertStatus(401);
    }

    public function test_owner_can_list_and_create_site_prompt(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/assistant/site-prompt')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.selected_key', null)
            ->assertJsonStructure([
                'data' => [
                    'prompts',
                    'catalog' => [
                        ['group', 'group_label', 'items' => [['key', 'section_key', 'label', 'helper', 'section_label', 'prompt_instruction', 'own_brand', 'owned', 'drifted']]],
                    ],
                    'default_instruction',
                    'recommended_label',
                    'embed' => ['snippet', 'api_base', 'script_url'],
                ],
            ]);

        $snippet = $list->json('data.embed.snippet');
        $this->assertIsString($snippet);
        $this->assertNotSame('', $snippet);
        $this->assertStringContainsString('data-cms8-widget="assistant"', $snippet);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $snippet);
        $this->assertStringContainsString('/js/cms8-widgets.js', $snippet);
        $this->assertStringNotContainsString('HUMANO', $snippet);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt', [
                'section_label' => 'Citas y tienda',
                'prompt_instruction' => 'Reservá citas y vendé el catálogo.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.selected_key', null);

        $this->assertNull($team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
        $this->assertNotNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'citas_y_tienda')
                ->first(),
        );
    }

    public function test_owner_can_select_existing_prompt(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Citas y catálogo.',
            'is_active' => true,
            'order' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:citas_y_ventas',
            ]);

        $response->assertOk()->assertJsonPath('data.selected_key', 'chat:citas_y_ventas');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/assistant/site-prompt', [
                'prompt_key' => TeamSiteAssistantPromptService::OFF_KEY,
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', TeamSiteAssistantPromptService::OFF_KEY);

        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
        $this->assertTrue(app(TeamSiteAssistantPromptService::class)->isSilentDefault($team->fresh()));
        $this->assertNull(app(TeamSiteAssistantPromptService::class)->resolvedRoutingKey($team->fresh()));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/assistant/site-prompt', [
                'prompt_key' => TeamSiteAssistantPromptService::FORCE_OFF_KEY,
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', TeamSiteAssistantPromptService::FORCE_OFF_KEY);

        $this->assertTrue(app(TeamSiteAssistantPromptService::class)->isForceSilent($team->fresh()));
        $this->assertFalse(app(TeamSiteAssistantPromptService::class)->isSilentDefault($team->fresh()));
        $this->assertNull(app(TeamSiteAssistantPromptService::class)->resolvedRoutingKey($team->fresh()));
        $snippet = $response->json('data.embed.snippet');
        $this->assertIsString($snippet);
        $this->assertStringContainsString('data-cms8-widget="assistant"', $snippet);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $snippet);
        $this->assertStringContainsString('/js/cms8-widgets.js', $snippet);
        $option = collect($response->json('data.prompts'))->firstWhere('key', 'chat:citas_y_ventas');
        $this->assertIsArray($option);
        $this->assertSame('Citas y catálogo.', $option['prompt_instruction']);
        $this->assertSame('Citas y ventas', $option['section_label']);
    }

    public function test_owner_can_update_selected_prompt_content(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Citas y catálogo.',
            'is_active' => true,
            'order' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:citas_y_ventas',
                'section_label' => 'Citas y tienda',
                'prompt_instruction' => 'Reservá citas y mostrá el catálogo actualizado.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.selected_key', null);

        $option = collect($response->json('data.prompts'))->firstWhere('key', 'chat:citas_y_ventas');
        $this->assertIsArray($option);
        $this->assertSame('Citas y tienda', $option['section_label']);
        $this->assertSame('Reservá citas y mostrá el catálogo actualizado.', $option['prompt_instruction']);

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'citas_y_ventas')
            ->first();
        $this->assertNotNull($prompt);
        $this->assertSame('Citas y tienda', $prompt->section_label);
        $this->assertSame('Reservá citas y mostrá el catálogo actualizado.', $prompt->prompt_instruction);
        $this->assertNull($team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
    }

    public function test_update_content_does_not_change_the_selected_site_prompt(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Citas y catálogo.',
            'is_active' => true,
            'order' => 0,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/assistant/site-prompt', [
                'prompt_key' => TeamSiteAssistantPromptService::OFF_KEY,
            ])
            ->assertOk();

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'otro_flujo',
            'section_label' => 'Otro flujo',
            'prompt_instruction' => 'Texto viejo.',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:otro_flujo',
                'section_label' => 'Otro flujo',
                'prompt_instruction' => 'Texto nuevo.',
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', TeamSiteAssistantPromptService::OFF_KEY);

        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
    }

    public function test_from_catalog_can_copy_without_selecting(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/assistant/site-prompt', [
                'prompt_key' => TeamSiteAssistantPromptService::OFF_KEY,
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt/from-catalog', [
                'prompt_key' => 'calendar:assistant_citas',
                'select' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', TeamSiteAssistantPromptService::OFF_KEY);

        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
        $this->assertNotNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'assistant_citas')
                ->first(),
        );
    }

    public function test_update_content_requires_fields(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/site-prompt', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt_key', 'section_label', 'prompt_instruction']);
    }

    /**
     * The Assistant offers the settings screen to every team administrator, so saving from it must
     * work for an admin who does not own the team.
     */
    public function test_admin_who_does_not_own_the_team_can_save_the_site_prompt(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $admin = User::factory()->create();
        $admin->teams()->attach($team->id, ['role' => 'editor']);
        $admin->forceFill(['current_team_id' => $team->id])->save();
        $admin->assignRole('admin');
        $adminToken = $admin->refresh()->createToken('assistant-admin-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->postJson('/api/assistant/site-prompt', [
                'section_label' => 'Citas y tienda',
                'prompt_instruction' => 'Reservá citas y vendé el catálogo.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.selected_key', null);
    }

    public function test_plain_member_cannot_save_the_site_prompt(): void
    {
        [, $team] = $this->assistantUserWithToken();

        $member = User::factory()->create();
        $member->teams()->attach($team->id, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $memberToken = $member->refresh()->createToken('assistant-member-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->postJson('/api/assistant/site-prompt', [
                'section_label' => 'Citas y tienda',
                'prompt_instruction' => 'Reservá citas y vendé el catálogo.',
            ])
            ->assertStatus(403);
    }

    public function test_catalog_lists_grouped_defaults_without_own_brand_scripts(): void
    {
        config(['humano_pricing.plan_access_team_ids' => []]);
        [, , $token] = $this->assistantUserWithToken();

        $items = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/assistant/site-prompt')
                ->assertOk()
                ->json('data.catalog'),
        )->flatMap(fn (array $group) => $group['items']);

        $this->assertTrue($items->contains(fn (array $item) => $item['key'] === 'calendar:assistant_citas'));
        $this->assertTrue($items->contains(fn (array $item) => $item['key'] === 'invoices:collections'));
        $this->assertFalse($items->contains(fn (array $item) => $item['key'] === 'products:humano_assistant'));
        $this->assertFalse($items->contains(fn (array $item) => $item['key'] === 'products:wapify_me'));
        $this->assertFalse($items->contains(fn (array $item) => $item['key'] === 'products:pumpstall'));
        $this->assertFalse($items->contains(fn (array $item) => str_starts_with((string) $item['key'], 'list60:')));
    }

    public function test_from_catalog_copies_the_php_default_and_selects_it(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_citas')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'TEXTO DEL EQUIPO';
        $prompt->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt/from-catalog', [
                'prompt_key' => 'calendar:assistant_citas',
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', 'calendar:assistant_citas');

        $this->assertSame('calendar:assistant_citas', $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
        $this->assertNotSame('TEXTO DEL EQUIPO', $prompt->fresh()->prompt_instruction);
        $this->assertStringContainsString('create_calendar_event', (string) $prompt->fresh()->prompt_instruction);

        $citas = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/assistant/site-prompt')
                ->json('data.catalog'),
        )->flatMap(fn (array $group) => $group['items'])->firstWhere('key', 'calendar:assistant_citas');
        $this->assertIsArray($citas);
        $this->assertTrue($citas['owned']);
        $this->assertFalse($citas['drifted']);
    }

    public function test_from_catalog_rejects_own_brand_for_a_regular_team(): void
    {
        config(['humano_pricing.plan_access_team_ids' => []]);
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt/from-catalog', [
                'prompt_key' => 'products:humano_assistant',
            ])
            ->assertStatus(422);
    }

    public function test_owner_can_delete_a_created_prompt_and_resets_the_selection(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt', [
                'section_label' => 'Mi flujo',
                'prompt_instruction' => 'Hablá solo de este negocio.',
            ])
            ->assertCreated();

        $created = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->putJson('/api/assistant/site-prompt', [
                    'prompt_key' => 'chat:mi_flujo',
                ])
                ->assertOk()
                ->assertJsonPath('data.selected_key', 'chat:mi_flujo')
                ->json('data.prompts'),
        )->firstWhere('key', 'chat:mi_flujo');
        $this->assertIsArray($created);
        $this->assertTrue($created['custom']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:mi_flujo',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.selected_key', TeamSiteAssistantPromptService::OFF_KEY);

        $this->assertNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'mi_flujo')
                ->first(),
        );
        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
        $this->assertFalse(
            collect(
                $this->withHeader('Authorization', 'Bearer '.$token)
                    ->getJson('/api/assistant/site-prompt')
                    ->json('data.prompts'),
            )->contains(fn (array $option) => $option['key'] === 'chat:mi_flujo'),
        );
    }

    public function test_owner_cannot_delete_a_system_default_prompt(): void
    {
        [, $team, $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/assistant/site-prompt/from-catalog', [
                'prompt_key' => 'calendar:assistant_citas',
            ])
            ->assertOk()
            ->assertJsonPath('data.selected_key', 'calendar:assistant_citas');

        $option = collect(
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/assistant/site-prompt')
                ->json('data.prompts'),
        )->firstWhere('key', 'calendar:assistant_citas');
        $this->assertIsArray($option);
        $this->assertFalse($option['custom']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/site-prompt', [
                'prompt_key' => 'calendar:assistant_citas',
            ])
            ->assertStatus(422);

        $this->assertNotNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'assistant_citas')
                ->first(),
        );
        $this->assertSame('calendar:assistant_citas', $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
    }

    public function test_delete_rejects_a_missing_prompt(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:no_existe',
            ])
            ->assertStatus(422);
    }

    public function test_plain_member_cannot_delete_the_site_prompt(): void
    {
        [, $team] = $this->assistantUserWithToken();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'mi_flujo',
            'section_label' => 'Mi flujo',
            'prompt_instruction' => 'Hablá solo de este negocio.',
            'is_active' => true,
            'order' => 10,
        ]);

        $member = User::factory()->create();
        $member->teams()->attach($team->id, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $memberToken = $member->refresh()->createToken('assistant-member-delete-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->deleteJson('/api/assistant/site-prompt', [
                'prompt_key' => 'chat:mi_flujo',
            ])
            ->assertStatus(403);
    }
}
