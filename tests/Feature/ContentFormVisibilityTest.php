<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\User;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentFormVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_merge_content_form_visibility_preserves_false_and_defaults_missing_keys(): void
    {
        $merged = ContentsSectionCategoryData::mergeContentFormVisibility([
            'show_title' => false,
        ]);

        $this->assertFalse($merged['show_title']);
        $this->assertTrue($merged['show_main_content']);
        $this->assertTrue($merged['show_multimedia']);
    }

    public function test_merge_content_locales_from_storage_null_returns_all_supported(): void
    {
        $locales = ContentsSectionCategoryData::mergeContentLocalesFromStorage(null);

        $this->assertSame(array_keys(ContentsSectionCategoryData::supportedLocaleLabels()), $locales);
    }

    public function test_merge_content_locales_from_request_empty_defaults_to_spanish(): void
    {
        $locales = ContentsSectionCategoryData::mergeContentLocalesFromRequest([]);

        $this->assertSame(['es_ES'], $locales);
    }

    public function test_content_edit_form_only_shows_configured_locale_tabs(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->firstOrCreate(
            ['key' => 'contents'],
            [
                'name' => 'Contents',
                'icon' => 'file-text',
                'description' => 'Test',
                'is_core' => true,
                'status' => 1,
            ],
        );

        $data = ContentsSectionCategoryData::obaAboutSection();
        $data['content_locales'] = ['es', 'en'];

        $sectionCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'ES EN only',
            'data' => $data,
        ]);

        $content = Content::create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'default',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'T'],
            'content' => ['es' => 'Body'],
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('contents.edit', $content));

        $response->assertOk();
        $response->assertSee('Español', false);
        $response->assertSee('English', false);
        $response->assertDontSee('Italiano', false);
    }

    public function test_content_edit_form_omits_title_input_when_disabled_on_section_category(): void
    {
        $user = $this->actingAdmin();
        $team = $user->currentTeam;

        $module = Module::query()->firstOrCreate(
            ['key' => 'contents'],
            [
                'name' => 'Contents',
                'icon' => 'file-text',
                'description' => 'Test',
                'is_core' => true,
                'status' => 1,
            ],
        );

        $data = ContentsSectionCategoryData::obaAboutSection();
        $data['content_form'] = array_merge(
            ContentsSectionCategoryData::defaultContentFormVisibility(),
            ['show_title' => false],
        );

        $sectionCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Section With Hidden Title',
            'data' => $data,
        ]);

        $content = Content::create([
            'team_id' => $team->id,
            'section_category_id' => $sectionCategory->id,
            'category_id' => null,
            'template' => 'default',
            'order' => 0,
            'status' => 3,
            'title' => ['es' => 'T'],
            'content' => ['es' => 'Body'],
            'data' => [],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('contents.edit', $content));

        $response->assertOk();
        $response->assertDontSee('id="title_es"', false);
    }
}
