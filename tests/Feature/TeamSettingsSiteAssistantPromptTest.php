<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsSiteAssistantPromptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Team}
     */
    protected function actingTeamAdmin(): array
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }

    protected function createTeamPrompt(Team $team, string $sectionKey = 'citas_y_ventas'): Prompt
    {
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        return Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => $sectionKey,
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas y vendé productos.',
            'is_active' => true,
            'order' => 0,
        ]);
    }

    public function test_chat_settings_shows_site_assistant_prompt_card(): void
    {
        [$user, $team] = $this->actingTeamAdmin();

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'chat']))
            ->assertOk()
            ->assertSee('name="prompt_key"', false)
            ->assertSee(route('help.chat-assistant').'#site-assistant-prompt', false);
    }

    public function test_owner_can_select_existing_prompt_and_gets_embed_snippet(): void
    {
        [$user, $team] = $this->actingTeamAdmin();
        $this->createTeamPrompt($team);

        $this->actingAs($user)
            ->post(route('team-settings.chat.site-assistant-prompt', $team), [
                'prompt_key' => 'chat:citas_y_ventas',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $team->refresh();
        $this->assertSame('chat:citas_y_ventas', $team->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));

        $automation = Automation::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('slug', TeamSiteAssistantPromptService::EMBED_SLUG)
            ->first();
        $this->assertNotNull($automation);
        $this->assertSame('chat:citas_y_ventas', $automation->entry_prompt_key);
        $this->assertTrue($automation->allowsChannel(Automation::CHANNEL_API));

        $html = $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'chat']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="site-assistant-embed-snippet"', $html);
        $this->assertStringContainsString('/api/embed/automation/'.$automation->public_token, $html);
        $this->assertStringContainsString('/js/cms8-widgets.js', $html);
        $this->assertStringContainsString('data-cms8-widget', $html);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $html);
        $this->assertStringNotContainsString('HUMANO_WIDGETS_API_BASE', $html);
    }

    public function test_chat_settings_always_shows_cms8_embed_snippet(): void
    {
        [$user, $team] = $this->actingTeamAdmin();

        $html = $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'chat']))
            ->assertOk()
            ->getContent();

        $automation = Automation::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('slug', TeamSiteAssistantPromptService::EMBED_SLUG)
            ->first();
        $this->assertNotNull($automation);
        $this->assertStringContainsString('id="site-assistant-embed-snippet"', $html);
        $this->assertStringContainsString('/api/embed/automation/'.$automation->public_token, $html);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $html);
        $this->assertStringContainsString('/js/cms8-widgets.js', $html);
    }

    public function test_owner_can_create_site_assistant_prompt(): void
    {
        [$user, $team] = $this->actingTeamAdmin();

        $this->actingAs($user)
            ->post(route('team-settings.chat.site-assistant-prompt.store', $team), [
                'section_label' => 'Citas y tienda',
                'prompt_instruction' => 'Ayudá a reservar citas y vender el catálogo.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $prompt = Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_label', 'Citas y tienda')
            ->first();
        $this->assertNotNull($prompt);
        $this->assertSame('citas_y_tienda', $prompt->section_key);
        $this->assertStringContainsString('catálogo', (string) $prompt->prompt_instruction);

        $team->refresh();
        $this->assertSame('chat:citas_y_tienda', $team->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
    }

    public function test_create_requires_label_and_instruction(): void
    {
        [$user, $team] = $this->actingTeamAdmin();

        $this->actingAs($user)
            ->from(route('team-settings.edit', ['team' => $team, 'group' => 'chat']))
            ->post(route('team-settings.chat.site-assistant-prompt.store', $team), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['section_label', 'prompt_instruction']);
    }
}
