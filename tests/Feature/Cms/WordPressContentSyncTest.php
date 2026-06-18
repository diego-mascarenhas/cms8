<?php

namespace Tests\Feature\Cms;

use App\Jobs\PullPostFromWordPressJob;
use App\Models\Post;
use App\Models\Team;
use App\Models\User;
use App\Services\Cms\WordPressContentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressContentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function syncTeam(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $team->setSetting('wordpress_url', 'https://wp.test');
        $team->setSetting('wordpress_username', 'admin');
        $team->setSetting('wordpress_application_password', 'pass word');
        $team->setSetting('wordpress_cms_sync_enabled', '1');

        return $team->refresh();
    }

    public function test_push_creates_post_in_wordpress_and_stores_wp_id(): void
    {
        Bus::fake();
        Http::fake([
            'wp.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 555,
                'modified_gmt' => '2026-06-18T10:00:00',
            ], 201),
        ]);

        $team = $this->syncTeam();
        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'My post',
            'post_name' => 'my-post',
        ]);

        $service = WordPressContentSyncService::make($team);
        $this->assertTrue($service->pushPost($post->fresh()));

        $this->assertSame(555, (int) $post->fresh()->wp_id);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/wp-json/wp/v2/posts'));
    }

    public function test_push_updates_existing_linked_post(): void
    {
        Bus::fake();
        Http::fake([
            'wp.test/wp-json/wp/v2/posts/777' => Http::response([
                'id' => 777,
                'modified_gmt' => '2026-06-18T11:00:00',
            ], 200),
        ]);

        $team = $this->syncTeam();
        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Linked',
            'post_name' => 'linked',
            'wp_id' => 777,
        ]);

        $service = WordPressContentSyncService::make($team);
        $this->assertTrue($service->pushPost($post->fresh()));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/wp-json/wp/v2/posts/777'));
    }

    public function test_pull_creates_local_post_from_wordpress_payload(): void
    {
        Bus::fake();
        $team = $this->syncTeam();
        $service = WordPressContentSyncService::make($team);

        $applied = $service->pullItem([
            'id' => 900,
            'slug' => 'from-wp',
            'status' => 'publish',
            'title' => ['rendered' => 'From WordPress'],
            'content' => ['rendered' => '<p>Body</p>'],
            'excerpt' => ['rendered' => 'Sum'],
            'menu_order' => 2,
            'parent' => 0,
            'modified_gmt' => '2026-06-18T12:00:00',
        ], 'post');

        $this->assertTrue($applied);
        $this->assertDatabaseHas('posts', [
            'team_id' => $team->id,
            'wp_id' => 900,
            'post_type' => 'post',
            'post_title' => 'From WordPress',
            'post_name' => 'from-wp',
        ]);
    }

    public function test_pull_skips_when_local_is_newer_last_write_wins(): void
    {
        Bus::fake();
        $team = $this->syncTeam();

        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Local newest',
            'post_name' => 'local-newest',
            'wp_id' => 901,
            'wp_modified_gmt' => Carbon::parse('2026-06-18T09:00:00'),
        ]);
        // Local edited after the WP change we are about to receive.
        Post::withoutGlobalScopes()->where('id', $post->id)->update([
            'post_modified_gmt' => Carbon::parse('2026-06-18T13:00:00'),
        ]);

        $service = WordPressContentSyncService::make($team);
        $applied = $service->pullItem([
            'id' => 901,
            'slug' => 'should-not-apply',
            'status' => 'publish',
            'title' => ['rendered' => 'WP older change'],
            'modified_gmt' => '2026-06-18T10:00:00',
        ], 'post');

        $this->assertFalse($applied);
        $this->assertSame('Local newest', $post->fresh()->post_title);
    }

    public function test_webhook_requires_valid_secret(): void
    {
        $team = $this->syncTeam();
        $team->setSetting('wordpress_webhook_secret', 'topsecret');

        $this->postJson("/api/wordpress/webhook/{$team->id}", [
            'id' => 5, 'type' => 'post', 'action' => 'updated',
        ], ['X-Humano-Secret' => 'wrong'])->assertStatus(401);
    }

    public function test_webhook_queues_pull_with_valid_secret(): void
    {
        Bus::fake();
        $team = $this->syncTeam();
        $team->setSetting('wordpress_webhook_secret', 'topsecret');

        $this->postJson("/api/wordpress/webhook/{$team->id}", [
            'id' => 42, 'type' => 'page', 'action' => 'updated',
        ], ['X-Humano-Secret' => 'topsecret'])->assertOk();

        Bus::assertDispatched(PullPostFromWordPressJob::class, fn ($job) => $job->wpId === 42
            && $job->type === 'page'
            && $job->teamId === $team->id);
    }
}
