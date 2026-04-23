<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\User;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamContentsApiSectionCategoryDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_contents_list_includes_section_category_data(): void
    {
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

        Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'timeline_item',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'Title'],
            'content' => ['es' => 'Body'],
            'data' => ['event_year' => 1987],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/team/contents?locale=es&status=3', [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.data.0.section_category.data.slug', ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT);
    }

    public function test_team_contents_filtered_by_section_slug(): void
    {
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

        Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'timeline_item',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'Title'],
            'content' => ['es' => 'Body'],
            'data' => ['event_year' => 1987],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->getJson('/api/team/contents?locale=es&status=3&section_slug='.ContentsSectionCategoryData::DEMO_SLUG_OBA_ABOUT, [
            'Authorization' => 'Bearer '.$tokenValue,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.data.0.title', 'Title');
        $response->assertJsonCount(1, 'data.data');
    }
}
