<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsChatTogglesTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_group_shows_assistant_toggles_parity_with_chat_sidebar(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $html = $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'chat']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="chat[whatsapp_driver]"', $html);
        $this->assertStringContainsString(__('Meta Cloud API'), $html);
        $this->assertStringContainsString(__('360dialog'), $html);
        $this->assertStringContainsString(__('MessageBird'), $html);
        $this->assertStringContainsString('name="chat[assistant_auto_respond]"', $html);
        $this->assertStringContainsString('name="chat[assistant_chat_stub]"', $html);
        $this->assertStringContainsString('name="chat[assistant_keyword_intent_routing]"', $html);
        $this->assertStringContainsString('name="chat[chat_ai_assistance_blocked]"', $html);
        $this->assertStringContainsString('name="chat[whatsapp_allow_closed_window]"', $html);

        $pAuto = str_contains($html, 'name="chat[assistant_auto_respond]"')
            ? strpos($html, 'name="chat[assistant_auto_respond]"') : 0;
        $pStub = strpos($html, 'name="chat[assistant_chat_stub]"');
        $pKw = strpos($html, 'name="chat[assistant_keyword_intent_routing]"');
        $pBlock = strpos($html, 'name="chat[chat_ai_assistance_blocked]"');
        $this->assertNotFalse($pAuto);
        $this->assertNotFalse($pStub);
        $this->assertNotFalse($pKw);
        $this->assertNotFalse($pBlock);
        $this->assertLessThan($pStub, $pAuto);
        $this->assertLessThan($pKw, $pStub);
        $this->assertLessThan($pBlock, $pKw);
    }

    public function test_update_chat_group_persists_assistant_auto_respond(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('assistant_auto_respond', '1', ['group' => 'chat', 'type' => 'boolean']);

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'chat' => [
                'assistant_auto_respond' => '0',
                'assistant_chat_stub' => '0',
                'assistant_keyword_intent_routing' => '0',
                'chat_ai_assistance_blocked' => '0',
            ],
        ])->assertRedirect();

        $team->refresh();
        $this->assertFalse($team->getSetting('assistant_auto_respond', true));
    }

    public function test_update_chat_group_persists_whatsapp_blacklist_as_text(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $blacklist = "34600000000\n34611111111";

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'chat' => [
                'assistant_auto_respond' => '1',
                'assistant_chat_stub' => '0',
                'assistant_keyword_intent_routing' => '0',
                'chat_ai_assistance_blocked' => '0',
                'assistant_whatsapp_blacklist_numbers' => $blacklist,
            ],
        ])->assertRedirect();

        $team->refresh();
        $this->assertSame($blacklist, (string) $team->getSetting('assistant_whatsapp_blacklist_numbers', ''));
    }
}
