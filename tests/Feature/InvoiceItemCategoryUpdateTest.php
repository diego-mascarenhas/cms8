<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\InvoiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceItemCategoryUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            InvoiceTypeSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_assign_and_clear_invoice_item_category(): void
    {
        [$user, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => $category->id,
                'category_name' => 'Hosting',
            ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => null,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => null,
                'category_name' => 'Sin categoría',
            ]);

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'category_id' => null,
        ]);
    }

    public function test_admin_can_change_existing_invoice_item_category(): void
    {
        [$user, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $otherCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'Consulting',
        ]);

        $item->forceFill(['category_id' => $category->id])->save();

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $otherCategory->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => $otherCategory->id,
                'category_name' => 'Consulting',
            ]);
    }

    public function test_rejects_category_from_another_module(): void
    {
        [$user, $team, $item] = $this->createTeamWithUncategorizedItem();

        $otherModule = Module::query()->create([
            'name' => 'Other',
            'key' => 'other-'.uniqid(),
            'icon' => 'ti-box',
            'description' => null,
            'is_core' => false,
            'status' => 1,
        ]);

        $invalidCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $otherModule->id,
            'name' => 'Wrong module',
        ]);

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $invalidCategory->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_non_admin_cannot_update_invoice_item_category(): void
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        [$admin, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => 'editor']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('user');

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $category->id,
            ])
            ->assertForbidden();
    }

    public function test_invoiced_lines_page_shows_category_badge_for_admin(): void
    {
        [$user, $team, $item] = $this->createTeamWithUncategorizedItem();

        $this->actingAs($user)
            ->get(route('finance-dashboard.invoiced-lines', [
                'operation' => 'buy',
                'year' => 2026,
                'month' => 0,
                'uncategorized' => 1,
            ]))
            ->assertOk()
            ->assertSee('line-category-badge', false)
            ->assertSee('lineCategoryModal', false)
            ->assertSee('Sin categoría');
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: InvoiceItem, 3: Category}
     */
    private function createTeamWithUncategorizedItem(): array
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

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'type_id' => 1,
            'operation' => 'buy',
            'number' => 'B-500',
            'date' => Carbon::create(2026, 3, 10)->toDateString(),
            'due_date' => Carbon::create(2026, 3, 30),
            'gross_amount' => 250,
            'discount' => 0,
            'total_amount' => 250,
            'balance' => 0,
            'status' => 2,
        ]);

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'category_id' => null,
            'description' => 'Uncategorized expense',
            'quantity' => 1,
            'unit_price' => 250,
            'discount' => 0,
        ]);

        return [$user, $team, $item, $category];
    }
}
