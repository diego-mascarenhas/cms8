<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamApiTokensMultiTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_have_multiple_api_tokens_and_all_authenticate(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $first = $team->createApiToken('MCP Idoneo', '*');
        $second = $team->createApiToken('Local Dev', 'read');

        $this->assertCount(2, $team->fresh()->getApiTokens());

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$first['plain'],
        ])->assertStatus(200);

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$second['plain'],
        ])->assertStatus(200);
    }

    public function test_generating_token_from_settings_does_not_revoke_existing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $existing = $team->createApiToken('Existing', '*');

        $this->actingAs($user)
            ->post(route('team-settings.generate-api-token', $team), [
                'name' => 'Second Token',
                'abilities' => '*',
            ])
            ->assertRedirect()
            ->assertSessionHas('new_token');

        $tokens = $team->fresh()->getApiTokens();
        $this->assertCount(2, $tokens);

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$existing['plain'],
        ])->assertStatus(200);
    }

    public function test_revoking_one_token_keeps_the_others(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $keep = $team->createApiToken('Keep', '*');
        $drop = $team->createApiToken('Drop', '*');

        $this->actingAs($user)
            ->delete(route('team-settings.revoke-api-token', $team), [
                'token_id' => $drop['token']['id'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertCount(1, $team->fresh()->getApiTokens());

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$drop['plain'],
        ])->assertStatus(401);

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$keep['plain'],
        ])->assertStatus(200);
    }

    public function test_find_by_plain_api_token_returns_the_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $created = $team->createApiToken('Estimator', '*');

        $found = \App\Models\Team::findByPlainApiToken($created['plain']);

        $this->assertNotNull($found);
        $this->assertSame($team->id, $found->id);
        $this->assertNull(\App\Models\Team::findByPlainApiToken('missing-token'));
    }

    public function test_legacy_single_token_setting_still_authenticates(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $plain = bin2hex(random_bytes(32));

        $team->setSetting('api_token_hash', hash('sha256', $plain), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $this->getJson('/api/team/posts', [
            'Authorization' => 'Bearer '.$plain,
        ])->assertStatus(200);
    }
}
