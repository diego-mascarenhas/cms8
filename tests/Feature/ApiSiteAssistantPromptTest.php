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
            ->assertJsonPath('data.selected_key', 'chat:citas_y_tienda');

        $this->assertSame('chat:citas_y_tienda', $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
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
            ->assertJsonPath('data.selected_key', 'chat:citas_y_ventas');

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
        $this->assertSame('chat:citas_y_ventas', $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
    }

    public function test_update_content_requires_fields(): void
    {
        [, , $token] = $this->assistantUserWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/assistant/site-prompt', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt_key', 'section_label', 'prompt_instruction']);
    }
}
