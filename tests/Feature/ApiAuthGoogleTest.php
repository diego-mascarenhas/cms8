<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiAuthGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'axis-google-client']);
    }

    public function test_google_login_issues_a_token_for_an_existing_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create([
            'email' => 'ana@example.com',
        ]);

        $this->fakeGoogleIdentity([
            'email' => 'Ana@example.com',
            'name' => 'Ana Pérez',
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('email', 'ana@example.com')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'current_team']);

        $this->assertSame(1, User::query()->count());
    }

    public function test_google_login_creates_a_user_and_personal_team_for_a_new_email(): void
    {
        Bus::fake();

        $this->fakeGoogleIdentity([
            'email' => 'nuevo@example.com',
            'name' => 'Nuevo Usuario',
        ]);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('email', 'nuevo@example.com')
            ->assertJsonPath('user.name', 'Nuevo Usuario')
            ->assertJsonPath('current_team.is_owner', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'current_team' => ['id', 'name']]);

        $user = User::query()->where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->current_team_id);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_google_login_rejects_a_token_for_another_client(): void
    {
        $this->fakeGoogleIdentity([
            'aud' => 'another-client',
            'email' => 'ana@example.com',
            'name' => 'Ana Pérez',
        ]);

        $this->postJson('/api/auth/google', [
            'id_token' => 'foreign-token',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'No se pudo verificar la cuenta de Google.');

        $this->assertSame(0, User::query()->count());
    }

    public function test_google_login_rejects_an_unverified_email(): void
    {
        $this->fakeGoogleIdentity([
            'email' => 'ana@example.com',
            'email_verified' => 'false',
            'name' => 'Ana Pérez',
        ]);

        $this->postJson('/api/auth/google', [
            'id_token' => 'unverified-token',
        ])->assertUnauthorized();

        $this->assertSame(0, User::query()->count());
    }

    public function test_google_login_requires_an_id_token(): void
    {
        $this->postJson('/api/auth/google', [])->assertStatus(422);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function fakeGoogleIdentity(array $overrides): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(array_merge([
                'aud' => 'axis-google-client',
                'email' => 'ana@example.com',
                'email_verified' => 'true',
                'iss' => 'https://accounts.google.com',
                'name' => 'Ana Pérez',
            ], $overrides)),
        ]);
    }
}
