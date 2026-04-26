<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProactiveWhatsAppOutreachFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_proactive_keyword_line_requires_admin(): void
    {
        $this->seed(ModuleSeeder::class);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = \App\Models\Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'user']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('user');

        $this->actingAs($user)->postJson(route('chat.assistant'), [
            'message' => 'demo +34600111222',
        ])->assertStatus(403);
    }
}
