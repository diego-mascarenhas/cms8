<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentsHistoryTimelineSectionTest extends TestCase
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

    public function test_contents_category_save_stores_slug_timeline_flag_and_heading(): void
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

        $payload = [
            'name' => 'OBA About API',
            'module_id' => $module->id,
            'order' => 0,
            'status' => 'on',
            'content_locales_present' => '1',
            'content_locales' => ['es'],
            'content_form' => [
                'show_title' => '1',
                'show_main_content' => '1',
                'show_subtitle' => '0',
                'show_url' => '0',
                'show_featured' => '0',
                'show_seo' => '0',
                'show_multimedia' => '0',
            ],
            'contents_section_slug' => 'My Custom SLUG',
            'history_section_heading' => 'KNOW OUR HISTORY',
            'page_sections' => [
                'history_timeline' => '1',
            ],
            'content_ordering' => [
                ['column' => 'order', 'direction' => 'asc'],
                ['column' => 'created_at', 'direction' => 'desc'],
            ],
        ];

        $this->actingAs($user)->post(route('categories.store'), $payload)
            ->assertRedirect();

        $category = Category::query()->where('team_id', $team->id)->where('name', 'OBA About API')->firstOrFail();
        $this->assertSame('my-custom-slug', $category->data['slug']);
        $this->assertTrue($category->data['page_sections']['history_timeline']);
        $this->assertSame('KNOW OUR HISTORY', $category->data['history']['heading']);
        $this->assertTrue($category->contentsPageSectionHistoryTimeline());
    }

    public function test_content_store_sets_timeline_item_template_when_section_flag_enabled(): void
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

        $section = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Timeline section',
            'data' => [
                'slug' => 'test-timeline',
                'page_sections' => ['history_timeline' => true],
                'content_locales' => ['es'],
                'content_form' => [
                    'show_title' => true,
                    'show_subtitle' => true,
                    'show_url' => true,
                    'show_main_content' => true,
                    'show_featured' => true,
                    'show_seo' => true,
                    'show_multimedia' => true,
                ],
            ],
        ]);

        $payload = [
            'section_category_id' => $section->id,
            'status' => '3',
            'order' => 0,
            'title_es' => 'A title',
            'content_es' => '<p>Body</p>',
        ];

        $this->actingAs($user)->post(route('contents.store'), $payload)->assertRedirect();

        $content = Content::query()->where('team_id', $team->id)->where('section_category_id', $section->id)->firstOrFail();
        $this->assertSame('timeline_item', $content->template);
    }
}
