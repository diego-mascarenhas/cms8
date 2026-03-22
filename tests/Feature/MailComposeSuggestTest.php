<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailComposeSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_suggest_returns_401_when_guest(): void
    {
        $response = $this->postJson(route('mail.compose-suggest'), [
            'hint' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_compose_suggest_returns_403_without_team(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('mail.compose-suggest'), [
            'hint' => 'Draft a follow-up',
        ]);

        $response->assertStatus(403);
    }

    public function test_compose_suggest_returns_body_when_authenticated_with_team(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        AnonymousAgent::fake(['Suggested email body text.']);

        $response = $this->actingAs($user)->postJson(route('mail.compose-suggest'), [
            'hint' => 'Write a short thank-you note.',
            'recipient_summary' => 'client@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'response' => 'Suggested email body text.',
        ]);
    }
}
