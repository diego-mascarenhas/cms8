<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Module;
use App\Services\ProjectCategoryLegacyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ProjectCategoryLegacyImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_all_requires_projects_module(): void
    {
        $service = app(ProjectCategoryLegacyImportService::class);

        $result = $service->importAllFromLegacy();

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('Projects module not found', (string) $result['message']);
    }

    public function test_import_all_creates_parent_and_children_by_name(): void
    {
        Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => true,
            'status' => 1,
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('padre', 40)->andReturnSelf();
        $query->shouldReceive('where')->with('estado', '>', 0)->andReturnSelf();
        $query->shouldReceive('orderBy')->with('orden')->andReturnSelf();
        $query->shouldReceive('orderBy')->with('categoria')->andReturnSelf();
        $query->shouldReceive('get')->with(['id', 'categoria', 'descripcion', 'orden', 'estado'])->andReturn(collect([
            (object) ['id' => 101, 'categoria' => 'Web', 'descripcion' => 'Web projects', 'orden' => 1, 'estado' => 1],
            (object) ['id' => 102, 'categoria' => 'Marketing', 'descripcion' => null, 'orden' => 2, 'estado' => 1],
        ]));

        $connection = Mockery::mock();
        $connection->shouldReceive('table')->with('categorias_generales')->andReturn($query);

        DB::shouldReceive('connection')->with('mysql_legacy')->andReturn($connection);

        $service = app(ProjectCategoryLegacyImportService::class);
        $result = $service->importAllFromLegacy();

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['total_legacy']);

        $projectsModuleId = (int) Module::query()->where('key', 'projects')->value('id');

        $parent = Category::query()
            ->where('module_id', $projectsModuleId)
            ->whereNull('parent_id')
            ->where('name', ProjectCategoryLegacyImportService::PARENT_CATEGORY_NAME)
            ->first();

        $this->assertNotNull($parent);

        $web = Category::query()
            ->where('module_id', $projectsModuleId)
            ->where('name', 'Web')
            ->first();

        $this->assertNotNull($web);
        $this->assertSame($parent->id, $web->parent_id);
        $this->assertNull($web->team_id);
        $this->assertSame(101, data_get($web->data, 'legacy_category_id'));
    }

    public function test_resolve_category_id_from_legacy_creates_by_name(): void
    {
        Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => true,
            'status' => 1,
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('id', 55)->andReturnSelf();
        $query->shouldReceive('value')->with('categoria')->andReturn('Web');

        $connection = Mockery::mock();
        $connection->shouldReceive('table')->with('categorias_generales')->andReturn($query);

        DB::shouldReceive('connection')->with('mysql_legacy')->andReturn($connection);

        $service = app(ProjectCategoryLegacyImportService::class);
        $categoryId = $service->resolveCategoryIdFromLegacy(55);

        $this->assertNotNull($categoryId);
        $this->assertSame('Web', Category::query()->find($categoryId)?->name);
    }

    public function test_analyze_reports_missing_and_ok(): void
    {
        $projects = Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => true,
            'status' => 1,
        ]);

        Category::query()->forceCreate([
            'id' => 101,
            'name' => 'Web',
            'module_id' => $projects->id,
            'team_id' => null,
            'parent_id' => null,
            'status' => true,
        ]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('padre', 40)->andReturnSelf();
        $query->shouldReceive('where')->with('estado', '>', 0)->andReturnSelf();
        $query->shouldReceive('orderBy')->with('orden')->andReturnSelf();
        $query->shouldReceive('orderBy')->with('categoria')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([
            (object) ['id' => 101, 'categoria' => 'Web', 'descripcion' => null, 'orden' => 1, 'estado' => 1],
            (object) ['id' => 202, 'categoria' => 'Marketing', 'descripcion' => null, 'orden' => 2, 'estado' => 1],
        ]));

        $connection = Mockery::mock();
        $connection->shouldReceive('table')->with('categorias_generales')->andReturn($query);
        DB::shouldReceive('connection')->with('mysql_legacy')->andReturn($connection);

        $service = app(ProjectCategoryLegacyImportService::class);
        $analysis = $service->analyze();

        $this->assertSame(2, $analysis['summary']['total']);
        $this->assertSame(1, $analysis['summary']['ok']);
        $this->assertSame(1, $analysis['summary']['missing']);
        $this->assertSame('ok', $analysis['rows'][0]['status']);
        $this->assertSame('missing', $analysis['rows'][1]['status']);
    }

    public function test_resolve_returns_null_for_empty_legacy_id(): void
    {
        $service = app(ProjectCategoryLegacyImportService::class);

        $this->assertNull($service->resolveCategoryIdFromLegacy(null));
        $this->assertNull($service->resolveCategoryIdFromLegacy(0));
    }
}
