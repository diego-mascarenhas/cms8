<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\User;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TeamContentsIndexCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_cache_invalidates_after_content_update(): void
    {
        Config::set('cache.team_contents_index_ttl', 3600);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $module = Module::query()->create([
            'name' => 'Contents',
            'key' => 'contents',
            'icon' => 'file-text',
            'description' => 'Test',
            'is_core' => true,
            'status' => 1,
        ]);

        $sectionCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Test Section',
            'data' => ContentsSectionCategoryData::obaAboutSection(),
        ]);

        $content = Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'timeline_item',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'First'],
            'content' => ['es' => 'Body'],
            'data' => ['event_year' => 1987],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $auth = ['Authorization' => 'Bearer '.$tokenValue];
        $slug = ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT;

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'First');

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'First');

        $content->title = ['es' => 'Second'];
        $content->save();

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Second');
    }

    public function test_index_forever_cache_invalidates_after_content_update(): void
    {
        Config::set('cache.team_contents_index_ttl', -1);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $module = Module::query()->create([
            'name' => 'Contents',
            'key' => 'contents',
            'icon' => 'file-text',
            'description' => 'Test',
            'is_core' => true,
            'status' => 1,
        ]);

        $sectionCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Test Section',
            'data' => ContentsSectionCategoryData::obaAboutSection(),
        ]);

        $content = Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'timeline_item',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'ForeverFirst'],
            'content' => ['es' => 'Body'],
            'data' => ['event_year' => 1987],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $auth = ['Authorization' => 'Bearer '.$tokenValue];
        $slug = ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT;

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'ForeverFirst');

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'ForeverFirst');

        $content->title = ['es' => 'ForeverSecond'];
        $content->save();

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'ForeverSecond');
    }

    public function test_index_respects_ttl_zero_to_disable_cache(): void
    {
        Config::set('cache.team_contents_index_ttl', 0);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $tokenValue = bin2hex(random_bytes(32));
        $team->setSetting('api_token_hash', hash('sha256', $tokenValue), [
            'group' => 'api',
            'is_encrypted' => true,
        ]);

        $module = Module::query()->create([
            'name' => 'Contents',
            'key' => 'contents',
            'icon' => 'file-text',
            'description' => 'Test',
            'is_core' => true,
            'status' => 1,
        ]);

        $sectionCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Test Section',
            'data' => ContentsSectionCategoryData::obaAboutSection(),
        ]);

        $content = Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'timeline_item',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'Alpha'],
            'content' => ['es' => 'Body'],
            'data' => ['event_year' => 1987],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $auth = ['Authorization' => 'Bearer '.$tokenValue];
        $slug = ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT;

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Alpha');

        $content->title = ['es' => 'Beta'];
        $content->save();

        $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.$slug, $auth)
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Beta');
    }
}
