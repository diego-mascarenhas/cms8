<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use App\Services\Finance\ServiceCategoryOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryOptionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_for_invoice_lines_exclude_inactive_categories(): void
    {
        $team = Team::factory()->create();
        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $activeParent = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
            'parent_id' => null,
            'status' => true,
        ]);

        $activeChild = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Cloud Starter',
            'parent_id' => $activeParent->id,
            'status' => true,
        ]);

        // Legacy "active" flag used on many hosting plans in production (status = 2).
        $legacyActiveChild = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting OpenStack Beginner',
            'parent_id' => $activeParent->id,
            'status' => 2,
        ]);

        $inactiveChild = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Legacy Plan',
            'parent_id' => $activeParent->id,
            'status' => false,
        ]);

        $inactiveParent = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Old Group',
            'parent_id' => null,
            'status' => false,
        ]);

        Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Orphan Active',
            'parent_id' => $inactiveParent->id,
            'status' => true,
        ]);

        $projectsModule = Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $newProjectsCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $projectsModule->id,
            'name' => 'Sistemas',
            'parent_id' => null,
            'status' => true,
        ]);

        $options = app(ServiceCategoryOptionsService::class)->optionsForInvoiceLines((int) $team->id);
        $ids = collect($options)->pluck('id')->all();

        $this->assertContains((int) $activeChild->id, $ids);
        $this->assertContains((int) $legacyActiveChild->id, $ids);
        $this->assertContains((int) $newProjectsCategory->id, $ids);
        $this->assertNotContains((int) $inactiveChild->id, $ids);
        $this->assertNotContains((int) $inactiveParent->id, $ids);
        $this->assertNotContains(
            (int) Category::query()->where('name', 'Orphan Active')->value('id'),
            $ids,
        );
    }

    public function test_belongs_to_team_invoice_line_category_accepts_legacy_active_status(): void
    {
        $team = Team::factory()->create();
        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'VPS OpenStack Comfort',
            'parent_id' => null,
            'status' => 2,
        ]);

        $service = app(ServiceCategoryOptionsService::class);

        $this->assertTrue($service->belongsToTeamInvoiceLineCategory((int) $team->id, (int) $category->id));
    }
}
