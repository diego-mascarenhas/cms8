<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
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
}
