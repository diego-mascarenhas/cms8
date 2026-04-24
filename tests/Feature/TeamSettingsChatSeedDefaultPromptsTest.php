<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSettingsChatSeedDefaultPromptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_seed_default_assistant_prompts_from_chat_settings(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
            ->post(route('team-settings.chat.seed-default-assistant-prompts', $team));

        $response->assertSessionHas('success');
        $response->assertRedirect();

        $this->assertNotNull(
            \App\Models\Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'assistant_citas')
                ->first(),
        );
    }
}
