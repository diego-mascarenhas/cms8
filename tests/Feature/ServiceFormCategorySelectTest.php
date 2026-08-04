<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceFormCategorySelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            CurrencySeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_service_create_form_uses_modal_style_category_select(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $root = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Emailer',
            'parent_id' => null,
            'status' => 1,
        ]);

        $child = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'SMTP Certificado',
            'parent_id' => $root->id,
            'status' => 1,
        ]);

        $grandChild = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'SMTP Nested',
            'parent_id' => $child->id,
            'status' => 1,
        ]);

        $leaf = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting Enthusiast',
            'parent_id' => $root->id,
            'status' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('service.create'))
            ->assertOk()
            ->assertSee('text-muted fw-light', false)
            ->assertSee('mb-1 mt-3', false)
            ->assertSee('id="category_id"', false)
            ->assertSee('select2-service-category', false)
            ->assertSee('data-module-key="services"', false)
            ->assertSee('value="'.$leaf->id.'"', false)
            ->assertSee('Hosting Enthusiast')
            ->assertSee('value="'.$grandChild->id.'"', false)
            ->assertSee('SMTP Certificado › SMTP Nested')
            ->assertSee('Sin categoría')
            ->assertSee('initServiceCategorySelect', false);
    }

    public function test_module_options_includes_nested_categories_under_root_group(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $root = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Emailer',
            'parent_id' => null,
            'status' => 1,
        ]);

        $child = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'SMTP Certificado',
            'parent_id' => $root->id,
            'status' => 1,
        ]);

        $grandChild = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'SMTP Nested',
            'parent_id' => $child->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('categories.module-options', ['module_key' => 'services']))
            ->assertOk();

        $groups = $response->json('groups');
        $emailerGroup = collect($groups)->firstWhere('label', 'Emailer');

        $this->assertNotNull($emailerGroup);
        $this->assertSame('group', $emailerGroup['type']);

        $labels = collect($emailerGroup['options'])->pluck('label')->all();
        $this->assertContains('SMTP Certificado', $labels);
        $this->assertContains('SMTP Certificado › SMTP Nested', $labels);
        $this->assertContains($grandChild->id, collect($emailerGroup['options'])->pluck('id')->all());
    }

    public function test_service_edit_form_keeps_selected_nested_category(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = Module::query()->create([
            'name' => 'Services',
            'key' => 'services',
            'icon' => 'ti-server',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $root = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
            'parent_id' => null,
            'status' => 1,
        ]);

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Cloud Starter',
            'parent_id' => $root->id,
            'status' => 1,
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Cloud plan',
            'data' => [],
            'currency_id' => 1,
            'price' => 10,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('service.edit', $service->id))
            ->assertOk()
            ->assertSee('select2-service-category', false)
            ->assertSee('value="'.$category->id.'"', false)
            ->assertSee('selected', false)
            ->assertSee('Cloud Starter');
    }
}
