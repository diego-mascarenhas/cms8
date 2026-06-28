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

        $this->assertSame($parent->id, $this->service->resolveCategoryId(9001));
        $this->assertSame($child->id, $this->service->resolveCategoryId(9100));
        $this->assertNull($this->service->resolveCategoryId(null));
        $this->assertNull($this->service->resolveCategoryId(9999));
    }
}
