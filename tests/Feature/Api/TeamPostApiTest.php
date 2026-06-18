<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PostTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPostApiTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithToken(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        PostTypeSeeder::seedForTeam($team);

        return [$team, $tokenValue];
    }

    public function test_index_requires_token(): void
    {
        $this->getJson('/api/team/posts')->assertStatus(401);
    }

    public function test_index_returns_published_posts_for_team(): void
    {
        [$team, $token] = $this->teamWithToken();

        Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Visible',
            'post_name' => 'visible',
        ]);
        Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_DRAFT,
            'post_title' => 'Hidden draft',
            'post_name' => 'hidden-draft',
        ]);

        $response = $this->getJson('/api/team/posts?post_type=post', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $titles = collect($response->json('data.data'))->pluck('title.rendered')->all();
        $this->assertContains('Visible', $titles);
        $this->assertNotContains('Hidden draft', $titles);
    }

    public function test_store_creates_post_with_meta_and_slug(): void
    {
        [$team, $token] = $this->teamWithToken();

        $response = $this->postJson('/api/team/posts', [
            'post_type' => 'post',
            'post_title' => 'Hello World',
            'post_content' => '<p>Body</p>',
            'post_status' => 'publish',
            'meta' => ['_humano_subtitle_en' => 'Subtitle'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_title' => 'Hello World',
            'post_name' => 'hello-world',
        ]);
        $this->assertDatabaseHas('postmeta', [
            'meta_key' => '_humano_subtitle_en',
            'meta_value' => 'Subtitle',
        ]);
    }

    public function test_team_token_cannot_read_other_team_posts(): void
    {
        [$team, $token] = $this->teamWithToken();

        $otherUser = User::factory()->create();
        $otherTeam = Team::factory()->create(['user_id' => $otherUser->id]);
        $otherPost = Post::withoutGlobalScopes()->create([
            'team_id' => $otherTeam->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Other team post',
        ]);

        $this->getJson('/api/team/posts/'.$otherPost->id, [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(404);
    }
}
