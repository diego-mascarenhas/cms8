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
}
