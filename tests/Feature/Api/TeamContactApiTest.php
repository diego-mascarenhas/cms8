<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamContactApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Team, 1: string}
     */
    private function teamWithToken(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $this->seed(ContactStatusSeeder::class);
        $this->seed(LanguageSeeder::class);

        return [$team, $tokenValue];
    }

    public function test_store_assigns_the_team_owner_and_saves_notes(): void
    {
        [$team, $token] = $this->teamWithToken();

        $response = $this->postJson('/api/team/contacts', [
            'name' => 'Diego Mascarenhas',
            'email' => 'hola@idoneo.dev',
            'notes' => 'Prueba de sistemas!',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Diego Mascarenhas')
            ->assertJsonPath('data.responsible_id', $team->user_id)
            ->assertJsonPath('data.data.notes', 'Prueba de sistemas!');

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'hola@idoneo.dev',
            'creator_id' => $team->user_id,
            'responsible_id' => $team->user_id,
            'status_id' => 1,
        ]);
    }
}
