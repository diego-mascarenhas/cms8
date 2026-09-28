<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarHelpCenterChatIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_navbar_includes_chat_assistant_link_when_team_has_chat_module(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $module = Module::query()->create([
            'name' => 'Chat',
            'key' => 'chat',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString(route('chat.index', ['view' => 'assistant']), $html);
        $this->assertStringContainsString('ti-messages', $html);
    }

    public function test_navbar_chat_badge_shows_plus_100_when_inbound_exceeds_100(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('whatsapp_from', '34999000111');

        $module = Module::query()->create([
            'name' => 'Chat',
            'key' => 'chat',
            'icon' => null,
            'description' => null,
            'is_core' => false,
            'group' => null,
            'order' => 0,
            'status' => 1,
        ]);
        $team->modules()->attach($module->id, ['status' => 1, 'settings' => null]);

        for ($i = 0; $i < 101; $i++)
        {
            \App\Models\Conversation::create([
                'message_sid' => 'SM_badge_'.$i,
                'channel' => 'whatsapp',
                'from' => '34600000'.str_pad((string) ($i % 50), 3, '0', STR_PAD_LEFT),
                'to' => '34999000111',
                'body' => 'msg '.$i,
                'status' => 'received',
                'direction' => 'inbound',
            ]);
        }

        $html = \Livewire\Livewire::actingAs($user->fresh())
            ->test(\App\Livewire\HelpCenterIcon::class)
            ->html();

        $this->assertStringContainsString('+100', $html);
    }
}
