<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSync;
use App\Models\Module;
use App\Models\Service;
use App\Models\StripeSubscription;
use App\Models\TeamUsageInvoice;
use App\Models\User;
use App\Services\Billing\ServiceCategoryAssignmentService;
use App\Services\Billing\StripeInvoiceItemImporter;
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

    public function test_invoice_item_category_updates_linked_service_bidirectionally(): void
    {
        [$user, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $otherCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'Consulting',
        ]);

        $invoice = $item->invoice()->withoutGlobalScopes()->first();
        $invoice->forceFill([
            'operation' => 'sell',
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_svc_1',
        ])->save();

        $sync = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_svc_1',
            'customer_id' => 'cus_svc_1',
            'status' => 'active',
            'plan_name' => 'Hosting',
        ]);

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $invoice->enterprise_id,
            'subscription_id' => $sync->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Hosting',
            'data' => [],
            'currency_id' => 1,
            'price' => 10,
            'discount' => 0,
            'frequency' => 1,
            'status' => 4,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_svc_1',
            'stripe_subscription_id' => 'sub_svc_1',
            'status' => 'paid',
            'currency' => 'eur',
            'total' => 250,
            'paid' => true,
        ]);

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

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'category_id' => $otherCategory->id,
        ]);
        $this->assertSame($otherCategory->id, (int) $service->fresh()->category_id);
    }

    public function test_usage_invoice_category_applies_to_team_usage_drafts_and_survives_resync(): void
    {
        [$user, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $consumo = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'Consumo IA y Mensajería',
        ]);

        $invoice = $item->invoice()->withoutGlobalScopes()->first();
        $invoice->forceFill([
            'operation' => 'sell',
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_usage_1',
            'status' => 9,
        ])->save();

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_usage_1',
            'status' => 'draft',
            'currency' => 'eur',
            'total' => 250,
            'paid' => false,
            'raw_payload' => [
                'metadata' => ['humano_usage' => '1'],
                'lines' => [
                    'data' => [[
                        'description' => 'Uncategorized expense',
                        'quantity' => 1,
                        'amount_excluding_tax' => 25000,
                        'tax_rates' => [],
                    ]],
                ],
            ],
        ]);

        TeamUsageInvoice::factory()->create([
            'team_id' => $team->id,
            'stripe_invoice_id' => 'in_usage_1',
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 25000,
            'period_from' => Carbon::create(2026, 3, 1)->startOfDay(),
            'period_to' => Carbon::create(2026, 4, 1)->startOfDay(),
        ]);

        $siblingInvoice = Invoice::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'enterprise_id' => $invoice->enterprise_id,
            'type_id' => 1,
            'operation' => 'sell',
            'number' => 'USAGE-2',
            'date' => Carbon::create(2026, 4, 10)->toDateString(),
            'gross_amount' => 100,
            'discount' => 0,
            'total_amount' => 100,
            'balance' => 100,
            'status' => 9,
            'source_provider' => 'stripe',
            'source_reference_id' => 'in_usage_2',
        ]);

        $siblingItem = InvoiceItem::query()->create([
            'invoice_id' => $siblingInvoice->id,
            'category_id' => null,
            'description' => 'Otro consumo',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 0,
        ]);

        InvoiceSync::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'external_id' => 'in_usage_2',
            'status' => 'draft',
            'currency' => 'eur',
            'raw_payload' => [
                'metadata' => ['humano_usage' => '1'],
                'lines' => [
                    'data' => [[
                        'description' => 'Otro consumo',
                        'quantity' => 1,
                        'amount_excluding_tax' => 10000,
                        'tax_rates' => [],
                    ]],
                ],
            ],
        ]);

        TeamUsageInvoice::factory()->create([
            'team_id' => $team->id,
            'stripe_invoice_id' => 'in_usage_2',
            'status' => TeamUsageInvoice::STATUS_DRAFT,
            'billed_cents' => 10000,
            'period_from' => Carbon::create(2026, 4, 1)->startOfDay(),
            'period_to' => Carbon::create(2026, 5, 1)->startOfDay(),
        ]);

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $consumo->id,
            ])
            ->assertOk()
            ->assertJsonPath('category_id', $consumo->id);

        $this->assertSame($consumo->id, (int) $siblingItem->fresh()->category_id);
        $this->assertSame(
            $consumo->id,
            app(ServiceCategoryAssignmentService::class)->usageCategoryIdForTeam((int) $team->id),
        );

        $sync = InvoiceSync::query()->where('external_id', 'in_usage_1')->first();
        app(StripeInvoiceItemImporter::class)->syncForInvoice($invoice->fresh(), $sync);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Uncategorized expense',
            'category_id' => $consumo->id,
        ]);
    }

    public function test_admin_can_assign_projects_module_category_to_invoice_item(): void
    {
        [$user, $team, $item, $category] = $this->createTeamWithUncategorizedItem();

        $projectsModule = Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'ti-folder',
            'description' => null,
            'is_core' => true,
            'status' => 1,
        ]);

        $desarrollos = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $projectsModule->id,
            'name' => 'Sitios Webs',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(route('invoice-items.category.update', $item), [
                'category_id' => $desarrollos->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => $desarrollos->id,
                'category_name' => 'Sitios Webs',
            ]);
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
