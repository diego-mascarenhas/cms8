<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TemplateHtmlGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TemplateGenerateHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_html_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson(route('template.generate-html'), [
            'prompt' => 'Newsletter with logo and CTA',
        ]);

        $response->assertStatus(401);
    }

    public function test_generate_html_returns_422_when_prompt_missing(): void
    {
        $user = $this->createUserWithTeamAndRole('admin');
        $this->giveTemplateEditPermission($user);

        $response = $this->actingAs($user)->postJson(route('template.generate-html'), []);

        $response->assertStatus(422);
    }

    public function test_generate_html_dispatches_job_and_returns_202_with_token(): void
    {
        $fakeHtml = '<table><tr><td style="padding: 10px;">Test email content</td></tr></table>';
        $this->mock(TemplateHtmlGenerationService::class, function ($mock) use ($fakeHtml): void
        {
            $mock->shouldReceive('generate')
                ->once()
                ->with('Newsletter with logo and CTA', \Mockery::any())
                ->andReturn(['success' => true, 'html' => $fakeHtml]);
        });

        $user = $this->createUserWithTeamAndRole('admin');
        $this->giveTemplateEditPermission($user);

        $response = $this->actingAs($user)->postJson(route('template.generate-html'), [
            'prompt' => 'Newsletter with logo and CTA',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['token']);
        $token = $response->json('token');

        $resultResponse = $this->actingAs($user)->getJson(route('template.generate-html.result', ['token' => $token]));
        $resultResponse->assertStatus(200);
        $resultResponse->assertJson(['status' => 'completed']);
        $this->assertStringContainsString('<table', $resultResponse->json('html'));
        $this->assertStringContainsString('Test email content', $resultResponse->json('html'));
    }

    public function test_generate_html_result_returns_failed_when_service_fails(): void
    {
        $this->mock(TemplateHtmlGenerationService::class, function ($mock): void
        {
            $mock->shouldReceive('generate')
                ->once()
                ->with(\Mockery::any(), \Mockery::any())
                ->andReturn(['success' => false, 'error' => 'AI rate limit exceeded']);
        });

        $user = $this->createUserWithTeamAndRole('admin');
        $this->giveTemplateEditPermission($user);

        $response = $this->actingAs($user)->postJson(route('template.generate-html'), [
            'prompt' => 'Some prompt',
        ]);

        $response->assertStatus(202);
        $token = $response->json('token');

        $resultResponse = $this->actingAs($user)->getJson(route('template.generate-html.result', ['token' => $token]));
        $resultResponse->assertStatus(200);
        $resultResponse->assertJson(['status' => 'failed', 'error' => 'AI rate limit exceeded']);
    }

    public function test_generate_html_result_returns_404_for_unknown_token(): void
    {
        $user = $this->createUserWithTeamAndRole('admin');
        $this->giveTemplateEditPermission($user);

        $response = $this->actingAs($user)->getJson(route('template.generate-html.result', ['token' => 'invalid-token']));

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Unknown or expired token.']);
    }

    private function createUserWithTeamAndRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    private function giveTemplateEditPermission(User $user): void
    {
        $permission = Permission::firstOrCreate(['name' => 'template.edit', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }
}
