<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Tests\TestCase;

class ChatAiTogglePreferenceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_toggle_preference_does_not_change_team_auto_respond_setting(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $team->setSetting('assistant_auto_respond', '0');

        $response = $this->actingAs($user)->patchJson(route('chat.ai-toggle-preference'), [
            'on' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $team->refresh();
        $this->assertSame('0', (string) $team->getSetting('assistant_auto_respond', '0'));

        $blockedExists = SettingsRepositoryFactory::create()
            ->checkIfPropertyExists('user_'.$user->id, 'chat_ai_assistance_blocked');

        $this->assertFalse($blockedExists, 'Allowing AI should not persist an opt-out row');
    }
}
