<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatTeamSettingsSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_chat_team_settings_from_sidebar(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('assistant_auto_respond', '0');

        $this->actingAs($user)->patchJson(route('chat.team-settings-sidebar'), [
            'key' => 'assistant_auto_respond',
            'on' => true,
        ])->assertOk()->assertJson(['success' => true]);

        $team->refresh();
        $this->assertSame('1', (string) $team->getSetting('assistant_auto_respond', '0'));
    }

    public function test_non_admin_cannot_update_chat_team_settings_from_sidebar(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'member']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('assistant_auto_respond', '0');

        $this->actingAs($user)->patchJson(route('chat.team-settings-sidebar'), [
            'key' => 'assistant_auto_respond',
            'on' => true,
        ])->assertForbidden();

        $team->refresh();
        $this->assertSame('0', (string) $team->getSetting('assistant_auto_respond', '0'));
    }

    public function test_deprecated_assistant_auto_respond_route_still_works_for_admin(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->patchJson(route('chat.assistant-auto-respond'), [
            'on' => true,
        ])->assertOk();
    }

    public function test_admin_can_toggle_assistant_auto_respond_admins_when_off(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->patchJson(route('chat.team-settings-sidebar'), [
            'key' => 'assistant_auto_respond_admins_when_off',
            'on' => true,
        ])->assertOk()->assertJson(['success' => true]);

        $team->refresh();
        $this->assertTrue((bool) $team->getSetting('assistant_auto_respond_admins_when_off', false));
    }

    public function test_admin_can_toggle_chat_conversation_visibility_sections(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user)->patchJson(route('chat.team-settings-sidebar'), [
            'key' => 'chat_show_assistant_conversations',
            'on' => true,
        ])->assertOk()->assertJson(['success' => true]);

        $this->actingAs($user)->patchJson(route('chat.team-settings-sidebar'), [
            'key' => 'chat_show_whatsapp_conversations',
            'on' => false,
        ])->assertOk()->assertJson(['success' => true]);

        $team->refresh();
        $this->assertTrue((bool) $team->getSetting('chat_show_assistant_conversations', false));
        $this->assertFalse((bool) $team->getSetting('chat_show_whatsapp_conversations', true));
    }

    public function test_chat_sidebar_shows_team_prompt_select_instead_of_assistant_toggles(): void
    {
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class, ModuleSeeder::class]);
        [$user] = $this->actingTeamAdmin();

        $html = $this->actingAs($user)
            ->get(route('chat.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="sidebar-team-prompt-key"', $html);
        $this->assertStringContainsString(__('team_settings.site_assistant.select_empty'), $html);
        $this->assertStringContainsString(__('team_settings.site_assistant.select_off'), $html);
        $this->assertStringContainsString(__('team_settings.site_assistant.select_off_all'), $html);
        $this->assertStringContainsString('catalog:calendar:assistant_citas', $html);
        $this->assertStringNotContainsString('id="sidebar-ai-replies-toggle"', $html);
        $this->assertStringNotContainsString('id="sidebar-default-assistant-flow-toggle"', $html);
        $this->assertStringNotContainsString('id="sidebar-assistant-keyword-routing-toggle"', $html);
        $this->assertStringContainsString('id="sidebar-show-whatsapp-conversations-toggle"', $html);
    }

    public function test_admin_can_set_team_default_prompt_from_sidebar(): void
    {
        $this->seed(ModuleSeeder::class);
        [$user, $team] = $this->actingTeamAdmin();

        $this->actingAs($user)->patchJson(route('chat.team-site-assistant-prompt'), [
            'prompt_key' => TeamSiteAssistantPromptService::OFF_KEY,
        ])->assertOk()->assertJson([
            'success' => true,
            'selected_key' => TeamSiteAssistantPromptService::OFF_KEY,
        ]);

        $this->assertTrue(app(TeamSiteAssistantPromptService::class)->isSilentDefault($team->fresh()));

        $this->actingAs($user)->patchJson(route('chat.team-site-assistant-prompt'), [
            'prompt_key' => TeamSiteAssistantPromptService::FORCE_OFF_KEY,
        ])->assertOk()->assertJson([
            'success' => true,
            'selected_key' => TeamSiteAssistantPromptService::FORCE_OFF_KEY,
        ]);

        $this->assertTrue(app(TeamSiteAssistantPromptService::class)->isForceSilent($team->fresh()));

        $this->actingAs($user)->patchJson(route('chat.team-site-assistant-prompt'), [
            'prompt_key' => '',
        ])->assertOk()->assertJson([
            'success' => true,
            'selected_key' => '',
        ]);

        $this->assertFalse(app(TeamSiteAssistantPromptService::class)->isSilentDefault($team->fresh()));
        $this->assertFalse(app(TeamSiteAssistantPromptService::class)->isForceSilent($team->fresh()));
        $this->assertNull(app(TeamSiteAssistantPromptService::class)->selectedRoutingKey($team->fresh()));
    }

    public function test_admin_can_apply_a_catalog_prompt_from_the_sidebar(): void
    {
        $this->seed(ModuleSeeder::class);
        [$user, $team] = $this->actingTeamAdmin();

        $this->actingAs($user)->patchJson(route('chat.team-site-assistant-prompt'), [
            'prompt_key' => 'catalog:calendar:assistant_citas',
        ])->assertOk()->assertJson([
            'success' => true,
            'selected_key' => 'calendar:assistant_citas',
        ]);

        $this->assertSame(
            'calendar:assistant_citas',
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
    }

    public function test_non_admin_cannot_set_team_default_prompt_from_sidebar(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'member']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)->patchJson(route('chat.team-site-assistant-prompt'), [
            'prompt_key' => TeamSiteAssistantPromptService::OFF_KEY,
        ])->assertForbidden();

        $this->assertNotSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY, ''),
        );
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function actingTeamAdmin(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return [$user, $team];
    }
}
