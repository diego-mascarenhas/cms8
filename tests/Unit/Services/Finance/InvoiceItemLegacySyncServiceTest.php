<?php

namespace Tests\Unit\Services\Finance;

use App\Models\Category;
use App\Models\Module;
use App\Models\User;
use App\Services\Finance\InvoiceItemLegacySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceItemLegacySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceItemLegacySyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceItemLegacySyncService::class);
    }

    public function test_upsert_category_from_legacy_row_preserves_legacy_id(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-briefcase',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $legacyRow = (object) [
            'id' => 4242,
            'categoria' => 'Legacy Hosting',
            'descripcion' => '<p>From CMS7</p>',
            'id_moneda' => 2,
            'valor' => 99.5,
            'descuento' => 0,
            'frecuencia' => 1,
            'id_tipo' => null,
            'caracteristicas' => null,
            'orden' => 3,
            'estado' => 1,
            'fecha_alta' => now(),
            'fecha_modificacion' => now(),
        ];

        $result = $this->service->upsertCategoryFromLegacyRow($legacyRow, $team->id, $module->id, null);

        $this->assertSame('imported', $result);
        $this->assertDatabaseHas('categories', [
            'id' => 4242,
            'name' => 'Legacy Hosting',
            'team_id' => $team->id,
            'module_id' => $module->id,
            'parent_id' => null,
            'order' => 3,
        ]);
        $this->assertSame(4242, $this->service->resolveCategoryId(4242, $team->id));
    }

    public function test_upsert_category_skips_when_id_belongs_to_another_team(): void
    {
        $ownerOne = User::factory()->withPersonalTeam()->create();
        $teamOne = $ownerOne->ownedTeams()->first();

        $ownerTwo = User::factory()->withPersonalTeam()->create();
        $teamTwo = $ownerTwo->ownedTeams()->first();

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-briefcase',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        Category::factory()->create([
            'id' => 6060,
            'team_id' => $teamOne->id,
            'module_id' => $module->id,
            'name' => 'Team one category',
            'parent_id' => null,
        ]);

        $legacyRow = (object) [
            'id' => 6060,
            'categoria' => 'Legacy overwrite attempt',
            'descripcion' => '',
            'id_moneda' => null,
            'valor' => null,
            'descuento' => null,
            'frecuencia' => null,
            'id_tipo' => null,
            'caracteristicas' => null,
            'orden' => 0,
            'estado' => 1,
            'fecha_alta' => now(),
            'fecha_modificacion' => now(),
        ];

        $result = $this->service->upsertCategoryFromLegacyRow($legacyRow, $teamTwo->id, $module->id, null);

        $this->assertSame('skipped_team_conflict', $result);
        $this->assertDatabaseHas('categories', [
            'id' => 6060,
            'name' => 'Team one category',
            'team_id' => $teamOne->id,
        ]);
        $this->assertNull($this->service->resolveCategoryId(6060, $teamTwo->id));
        $this->assertSame(6060, $this->service->resolveCategoryId(6060, $teamOne->id));
    }

    public function test_upsert_category_from_legacy_row_restores_soft_deleted_category_with_same_id(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-briefcase',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'id' => 5151,
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Old name',
            'parent_id' => null,
        ]);
        $category->delete();

        $legacyRow = (object) [
            'id' => 5151,
            'categoria' => 'Restored from legacy',
            'descripcion' => '',
            'id_moneda' => null,
            'valor' => null,
            'descuento' => null,
            'frecuencia' => null,
            'id_tipo' => null,
            'caracteristicas' => null,
            'orden' => 0,
            'estado' => 1,
            'fecha_alta' => now(),
            'fecha_modificacion' => now(),
        ];

        $result = $this->service->upsertCategoryFromLegacyRow($legacyRow, $team->id, $module->id, null);

        $this->assertSame('updated', $result);
        $this->assertDatabaseHas('categories', [
            'id' => 5151,
            'name' => 'Restored from legacy',
            'deleted_at' => null,
        ]);
    }

    public function test_resolve_category_id_returns_direct_category_match(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-briefcase',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $parent = Category::factory()->create([
            'id' => 9001,
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
            'parent_id' => null,
        ]);

        $child = Category::factory()->create([
            'id' => 9100,
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Standard plan',
            'parent_id' => $parent->id,
        ]);

        $this->assertSame($parent->id, $this->service->resolveCategoryId(9001, $team->id));
        $this->assertSame($child->id, $this->service->resolveCategoryId(9100, $team->id));
        $this->assertNull($this->service->resolveCategoryId(null, $team->id));
        $this->assertNull($this->service->resolveCategoryId(9999, $team->id));
    }
}
