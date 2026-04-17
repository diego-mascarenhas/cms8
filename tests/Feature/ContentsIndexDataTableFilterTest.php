<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentsIndexDataTableFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_request_applies_section_id_query_param(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

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

        $sectionA = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Section A',
        ]);

        $sectionB = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Section B',
        ]);

        Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionA->id,
            'category_id' => null,
            'template' => null,
            'order' => 0,
            'status' => 3,
            'featured' => false,
            'featured_slide' => false,
            'featured_modal' => false,
            'title' => ['es' => 'Only A'],
            'content' => ['es' => ''],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Content::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'section_category_id' => $sectionB->id,
            'category_id' => null,
            'template' => null,
            'order' => 0,
            'status' => 3,
            'featured' => false,
            'featured_slide' => false,
            'featured_modal' => false,
            'title' => ['es' => 'Only B'],
            'content' => ['es' => ''],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $columnKeys = ['id', 'title', 'section_category_id', 'status', 'featured', 'created_at', 'action'];
        $columns = [];
        foreach ($columnKeys as $data)
        {
            $columns[] = [
                'data' => $data,
                'name' => $data,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        $query = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'section_id' => $sectionA->id,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 5, 'dir' => 'desc']],
            'columns' => $columns,
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get(route('contents.index').'?'.$query);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertSame('Only A', strip_tags((string) data_get($response->json('data.0'), 'title')));
    }
}
