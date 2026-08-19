<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromptPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_requires_at_least_one_of_text_image_or_audio(): void
    {
        $user = $this->createUserWithTeamAndRole('admin');
        $prompt = $this->createPrompt($user->current_team_id);

        $this->actingAs($user);

        $response = $this->postJson(route('prompt.preview', $prompt), [
            'test_message' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_preview_returns_text_response_with_test_message(): void
    {
        AnonymousAgent::fake(['Faked AI response text.']);

        $user = $this->createUserWithTeamAndRole('admin');
        $prompt = $this->createPrompt($user->current_team_id);

        $this->actingAs($user);

        $response = $this->postJson(route('prompt.preview', $prompt), [
            'test_message' => 'User input for test.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'response' => 'Faked AI response text.',
        ]);
    }

    public function test_preview_denied_for_guest(): void
    {
        $team = Team::factory()->create();
        $prompt = $this->createPrompt($team->id);

        $response = $this->postJson(route('prompt.preview', $prompt), [
            'test_message' => 'Some text',
        ]);

        $response->assertStatus(401);
    }

    public function test_preview_denied_for_non_admin(): void
    {
        $user = $this->createUserWithTeamAndRole('user');
        $prompt = $this->createPrompt($user->current_team_id);

        $this->actingAs($user);

        $response = $this->postJson(route('prompt.preview', $prompt), [
            'test_message' => 'Some text',
        ]);

        $response->assertStatus(403);
    }

    private function createPrompt(?int $teamId = null): Prompt
    {
        $module = Module::firstOrCreate(
            ['key' => 'test-module'],
            ['name' => 'Test Module', 'description' => 'Test', 'is_core' => 0, 'status' => 1, 'order' => 0],
        );

        $teamId = $teamId ?? Team::factory()->create()->id;

        return Prompt::withoutGlobalScope('team')->create([
            'team_id' => $teamId,
            'module_id' => $module->id,
            'section_key' => 'test_section',
            'section_label' => 'Test Section',
            'prompt_instruction' => 'You are a helpful assistant.',
            'is_active' => true,
            'order' => 0,
        ]);
    }

    private function createUserWithTeamAndRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        $this->enableTeamModules($team, ['prompts']);

        return $user->refresh();
    }
}
