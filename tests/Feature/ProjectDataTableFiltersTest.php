<?php

namespace Tests\Feature;

use App\DataTables\ProjectDataTable;
use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectDataTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Enterprise $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->user = User::factory()->withPersonalTeam()->create();
        $team = $this->user->ownedTeams()->first();
        $this->user->forceFill(['current_team_id' => $team->id])->save();
        $this->user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => true,
            'status' => 1,
        ]);

        $parent = Category::query()->create([
            'name' => 'Desarrollos',
            'module_id' => $module->id,
            'team_id' => null,
            'parent_id' => null,
            'status' => 2,
            'order' => 1,
        ]);

        $this->category = Category::query()->create([
            'name' => 'Sitios Webs',
            'module_id' => $module->id,
            'team_id' => null,
            'parent_id' => $parent->id,
            'status' => 2,
            'order' => 1,
        ]);

        $this->client = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Filtros',
            'type_id' => 1,
            'status_id' => 1,
        ]);
    }

    public function test_project_datatable_uses_spanish_language_file(): void
    {
        $options = app(ProjectDataTable::class)->html()->getOptions();

        $this->assertSame('/js/datatables/es.json', data_get($options, 'language.url'));
        $this->assertFileExists(public_path('js/datatables/es.json'));
    }

    public function test_project_datatable_filters_by_status_and_category(): void
    {
        $teamId = (int) $this->user->current_team_id;

        Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $this->client->id,
            'name' => 'Web Matching',
            'responsible_id' => $this->user->id,
            'status_id' => 9,
            'category_id' => $this->category->id,
        ]);

        Project::withoutGlobalScopes()->create([
            'team_id' => $teamId,
            'enterprise_id' => $this->client->id,
            'name' => 'Budget Other',
            'responsible_id' => $this->user->id,
            'status_id' => 1,
            'category_id' => null,
        ]);

        $response = $this->actingAs($this->user)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($this->projectDataTableUrl([
            'status_filter' => '3,7,8,9',
            'category_filter' => (string) $this->category->id,
        ]));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('recordsFiltered'));
        $this->assertStringContainsString('Web Matching', (string) json_encode($response->json('data')));
        $this->assertStringNotContainsString('Budget Other', (string) json_encode($response->json('data')));
    }

    public function test_project_list_page_includes_filter_controls_markup(): void
    {
        $response = $this->actingAs($this->user)->get(route('project-list'));

        $response->assertOk();
        $response->assertSee('project-filter-status', false);
        $response->assertSee('project-filter-category', false);
        $response->assertSee('status_filter', false);
        $response->assertSee('category_filter', false);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function projectDataTableUrl(array $filters = []): string
    {
        $columns = [];
        foreach ([
            ['data' => 'id', 'name' => 'id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'enterprise_id', 'name' => 'enterprise_id', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'category_id', 'name' => 'category_id', 'searchable' => 'true', 'orderable' => 'false'],
            ['data' => 'responsible_name', 'name' => 'responsible_name', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'date_end', 'name' => 'date_end', 'searchable' => 'false', 'orderable' => 'false'],
            ['data' => 'status_id', 'name' => 'status_id', 'searchable' => 'true', 'orderable' => 'true'],
            ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
        ] as $definition)
        {
            $columns[] = array_merge($definition, [
                'search' => ['value' => '', 'regex' => 'false'],
            ]);
        }

        $query = array_merge([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'columns' => $columns,
            'status_filter' => '',
            'category_filter' => '',
        ], $filters);

        return route('project-list').'?'.http_build_query($query);
    }
}
