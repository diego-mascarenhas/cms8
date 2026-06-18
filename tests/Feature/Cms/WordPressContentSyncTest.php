<?php

namespace Tests\Feature\Cms;

use App\Jobs\PullPostFromWordPressJob;
use App\Models\Post;
use App\Models\Team;
use App\Models\Term;
use App\Models\TermTaxonomy;
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
            'wp.test/wp-json/' => Http::response(['namespaces' => ['wp/v2']], 200),
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
            'wp.test/wp-json/' => Http::response(['namespaces' => ['wp/v2']], 200),
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

    public function test_pull_syncs_categories_and_tags_from_wordpress_payload(): void
    {
        Bus::fake();
        Http::fake([
            'wp.test/wp-json/' => Http::response(['namespaces' => ['wp/v2']], 200),
            'wp.test/wp-json/wp/v2/categories/3' => Http::response([
                'id' => 3,
                'name' => 'News',
                'slug' => 'news',
                'description' => '',
                'parent' => 0,
                'count' => 1,
            ], 200),
            'wp.test/wp-json/wp/v2/tags/7' => Http::response([
                'id' => 7,
                'name' => 'Launch',
                'slug' => 'launch',
                'description' => '',
                'count' => 1,
            ], 200),
        ]);

        $team = $this->syncTeam();
        $service = WordPressContentSyncService::make($team);

        $service->pullItem([
            'id' => 901,
            'slug' => 'with-terms',
            'status' => 'publish',
            'title' => ['rendered' => 'With terms'],
            'content' => ['rendered' => '<p>Body</p>'],
            'categories' => [3],
            'tags' => [7],
            'modified_gmt' => '2026-06-18T12:00:00',
        ], 'post');

        $this->assertDatabaseHas('terms', [
            'team_id' => $team->id,
            'wp_id' => 3,
            'slug' => 'news',
        ]);
        $this->assertDatabaseHas('terms', [
            'team_id' => $team->id,
            'wp_id' => 7,
            'slug' => 'launch',
        ]);

        $post = Post::withoutGlobalScopes()->where('wp_id', 901)->first();
        $this->assertNotNull($post);
        $this->assertCount(2, $post->termTaxonomies);
    }

    public function test_sync_all_terms_pulls_categories_and_tags(): void
    {
        Bus::fake();
        Http::fake([
            'wp.test/wp-json/' => Http::response(['namespaces' => ['wp/v2']], 200),
            'wp.test/wp-json/wp/v2/categories*' => Http::response([
                [
                    'id' => 3,
                    'name' => 'News',
                    'slug' => 'news',
                    'description' => 'Latest',
                    'parent' => 0,
                    'count' => 2,
                ],
            ], 200),
            'wp.test/wp-json/wp/v2/tags*' => Http::response([
                [
                    'id' => 7,
                    'name' => 'Launch',
                    'slug' => 'launch',
                    'description' => '',
                    'count' => 1,
                ],
            ], 200),
        ]);

        $team = $this->syncTeam();
        WordPressContentSyncService::make($team)->syncAllTerms();

        $this->assertDatabaseHas('terms', [
            'team_id' => $team->id,
            'wp_id' => 3,
            'name' => 'News',
        ]);
        $this->assertDatabaseHas('term_taxonomy', [
            'team_id' => $team->id,
            'taxonomy' => 'category',
            'description' => 'Latest',
        ]);
        $this->assertDatabaseHas('terms', [
            'team_id' => $team->id,
            'wp_id' => 7,
            'slug' => 'launch',
        ]);
    }

    public function test_sync_all_pushes_linked_posts_when_local_is_newer(): void
    {
        Bus::fake();
        Http::fake(function ($request)
        {
            $url = $request->url();

            if (str_contains($url, '/wp-json/') && ! str_contains($url, 'rest_route'))
            {
                if ($request->method() === 'GET' && preg_match('#/wp-json/?$#', $url))
                {
                    return Http::response(['namespaces' => ['wp/v2']], 200);
                }

                if ($request->method() === 'PUT' && str_contains($url, '/wp/v2/pages/42'))
                {
                    return Http::response([
                        'id' => 42,
                        'modified_gmt' => '2026-06-18T14:00:00',
                    ], 200);
                }

                if ($request->method() === 'GET')
                {
                    return Http::response([], 200);
                }
            }

            return Http::response([], 404);
        });

        $team = $this->syncTeam();
        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'page',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Inicio sync',
            'post_name' => 'inicio-sync',
            'wp_id' => 42,
            'wp_modified_gmt' => Carbon::parse('2026-06-18T09:00:00'),
            'post_modified_gmt' => Carbon::parse('2026-06-18T13:00:00'),
        ]);

        $result = WordPressContentSyncService::make($team)->syncAll();

        $this->assertSame(1, $result['pushed']);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/wp-json/wp/v2/pages/42')
            && ($request->data()['title'] ?? null) === 'Inicio sync');
    }

    public function test_push_post_creates_local_category_on_wordpress_and_assigns_it(): void
    {
        Bus::fake();
        Http::fake(function ($request)
        {
            $url = $request->url();

            if ($request->method() === 'GET' && (str_contains($url, '/wp-json/') || str_contains($url, 'rest_route=')))
            {
                if (str_contains($url, '/wp-json/?') || preg_match('#/wp-json/?$#', parse_url($url, PHP_URL_PATH) ?? ''))
                {
                    return Http::response(['namespaces' => ['wp/v2']], 200);
                }

                return Http::response([], 200);
            }

            if ($request->method() === 'POST' && (str_contains($url, '/wp/v2/categories') || str_contains($url, 'rest_route=%2Fwp%2Fv2%2Fcategories')))
            {
                return Http::response(['id' => 99, 'slug' => 'desde-el-mac'], 201);
            }

            if ($request->method() === 'PUT' && (str_contains($url, '/wp/v2/posts/5') || str_contains($url, 'rest_route=%2Fwp%2Fv2%2Fposts%2F5')))
            {
                return Http::response(['id' => 5, 'modified_gmt' => '2026-06-18T14:00:00'], 200);
            }

            return Http::response([], 404);
        });

        $team = $this->syncTeam();
        $term = Term::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Desde el Mac',
            'slug' => 'desde-el-mac',
        ]);
        $termTaxonomy = TermTaxonomy::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'term_id' => $term->id,
            'taxonomy' => TermTaxonomy::TAXONOMY_CATEGORY,
        ]);

        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'Nueva',
            'post_name' => 'nueva',
            'wp_id' => 5,
        ]);
        $post->termTaxonomies()->sync([$termTaxonomy->id => ['team_id' => $team->id]]);

        $this->assertTrue(WordPressContentSyncService::make($team)->pushPost($post->fresh(['termTaxonomies.term'])));
        $this->assertSame(99, (int) $term->fresh()->wp_id);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/wp-json/wp/v2/posts/5')
            && ($request->data()['categories'] ?? []) === [99]);
    }

    public function test_pull_stores_excerpt_as_plain_text(): void
    {
        Bus::fake();
        $team = $this->syncTeam();
        $service = WordPressContentSyncService::make($team);

        $service->pullItem([
            'id' => 930,
            'slug' => 'excerpt-html',
            'status' => 'publish',
            'title' => ['rendered' => 'Excerpt HTML'],
            'content' => ['rendered' => '<p>Body</p>'],
            'excerpt' => ['rendered' => "<p>Resumen &amp; cosas</p>\n"],
            'modified_gmt' => '2026-06-18T12:00:00',
        ], 'post');

        $post = Post::withoutGlobalScopes()->where('wp_id', 930)->first();
        $this->assertSame('Resumen & cosas', $post->post_excerpt);
    }

    public function test_pull_stores_custom_fields_from_wordpress_payload(): void
    {
        Bus::fake();
        $team = $this->syncTeam();
        $service = WordPressContentSyncService::make($team);

        $service->pullItem([
            'id' => 920,
            'slug' => 'with-cf',
            'status' => 'publish',
            'title' => ['rendered' => 'With custom fields'],
            'content' => ['rendered' => '<p>Body</p>'],
            'modified_gmt' => '2026-06-18T12:00:00',
            'icf_fields' => [
                'subtitle' => 'Hola mundo',
                'slides' => [
                    ['title' => 'Slide 1'],
                    ['title' => 'Slide 2'],
                ],
            ],
        ], 'post');

        $post = Post::withoutGlobalScopes()->where('wp_id', 920)->first();
        $this->assertNotNull($post);
        $stored = json_decode((string) $post->getMeta('_icf_fields'), true);
        $this->assertSame('Hola mundo', $stored['subtitle']);
        $this->assertCount(2, $stored['slides']);
    }

    public function test_push_post_sends_custom_fields_to_wordpress(): void
    {
        Bus::fake();
        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'GET' && preg_match('#/wp-json/?$#', parse_url($url, PHP_URL_PATH) ?? '')) {
                return Http::response(['namespaces' => ['wp/v2']], 200);
            }
            if ($request->method() === 'PUT' && str_contains($url, '/wp/v2/posts/5')) {
                return Http::response(['id' => 5, 'modified_gmt' => '2026-06-18T14:00:00'], 200);
            }
            return Http::response([], 404);
        });

        $team = $this->syncTeam();
        $post = Post::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'post_type' => 'post',
            'post_status' => Post::STATUS_PUBLISH,
            'post_title' => 'CF push',
            'post_name' => 'cf-push',
            'wp_id' => 5,
        ]);
        $post->setMeta('_icf_fields', json_encode(['subtitle' => 'Hola', 'cta' => 'Click']));

        $this->assertTrue(WordPressContentSyncService::make($team)->pushPost($post->fresh(['meta', 'termTaxonomies.term'])));

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_contains($request->url(), '/wp-json/wp/v2/posts/5')
            && ($request->data()['icf_fields']['subtitle'] ?? null) === 'Hola'
            && ($request->data()['icf_fields']['cta'] ?? null) === 'Click');
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
