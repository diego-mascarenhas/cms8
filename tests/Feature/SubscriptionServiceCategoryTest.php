<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Service;
use App\Models\StripeSubscription;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionServiceCategoryTest extends TestCase
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

    public function test_admin_can_update_linked_service_category(): void
    {
        [$user, $team, $sync, $service, $category] = $this->createSubscriptionWithService();

        $otherCategory = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'VPS',
        ]);

        $this->actingAs($user)
            ->patchJson(route('subscription.stripe-service-category.update', $sync), [
                'category_id' => $otherCategory->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'service_id' => $service->id,
                'category_id' => $otherCategory->id,
                'category_name' => 'VPS',
            ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'category_id' => $otherCategory->id,
        ]);
    }

    public function test_admin_can_clear_linked_service_category(): void
    {
        [$user, $team, $sync, $service] = $this->createSubscriptionWithService();

        $this->actingAs($user)
            ->patchJson(route('subscription.stripe-service-category.update', $sync), [
                'category_id' => null,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => null,
            ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'category_id' => null,
        ]);
    }

    public function test_admin_can_create_service_from_subscription(): void
    {
        [$user, $team, $sync, $category] = $this->createSubscriptionWithoutService();

        $this->actingAs($user)
            ->postJson(route('subscription.stripe-create-service', $sync), [
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category_id' => $category->id,
                'category_name' => 'Hosting',
            ]);

        $this->assertDatabaseHas('services', [
            'subscription_id' => $sync->id,
            'category_id' => $category->id,
            'operation' => 'sell',
        ]);
    }

    public function test_create_service_requires_linked_client(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->createServicesModule();

        $sync = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_orphan_1',
            'customer_id' => 'cus_orphan_1',
            'customer_name' => 'Orphan Customer',
            'status' => 'active',
            'plan_name' => 'Hosting',
        ]);

        $this->actingAs($user)
            ->postJson(route('subscription.stripe-create-service', $sync), [
                'category_id' => null,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_subscription_index_includes_category_modal_for_admin(): void
    {
        [$user] = $this->createSubscriptionWithoutService();

        $this->actingAs($user)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertSee('lineCategoryModal', false);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: StripeSubscription, 3: Service, 4: Category}
     */
    private function createSubscriptionWithService(): array
    {
        [$user, $team, $sync, $category, $enterprise] = $this->createSubscriptionWithoutService();

        $service = Service::withoutGlobalScopes()->create([
            'enterprise_id' => $enterprise->id,
            'subscription_id' => $sync->id,
            'category_id' => $category->id,
            'operation' => 'sell',
            'description' => 'Hosting Enthusiast',
            'data' => [],
            'currency_id' => 1,
            'price' => 19.99,
            'discount' => 0,
            'frequency' => 1,
            'next_billing' => now()->addMonth(),
            'responsible_id' => $user->id,
            'status' => 4,
        ]);

        return [$user, $team, $sync, $service, $category];
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: StripeSubscription, 3: Category, 4: Enterprise}
     */
    private function createSubscriptionWithoutService(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $module = $this->createServicesModule();

        $category = Category::factory()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'name' => 'Hosting',
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Acme SL',
            'code' => 'cus_test_123',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $sync = StripeSubscription::query()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'stripe_id' => 'sub_test_123',
            'customer_id' => 'cus_test_123',
            'customer_name' => 'Acme SL',
            'status' => 'active',
            'plan_name' => 'Hosting Enthusiast',
            'price_currency' => 'eur',
            'amount_total' => 19.99,
            'current_period_end' => now()->addMonth(),
        ]);

        return [$user, $team, $sync, $category, $enterprise];
    }

    private function createServicesModule(): Module
    {
        return Module::query()->firstOrCreate(
            ['key' => 'services'],
            [
                'name' => 'Services',
                'icon' => 'ti-server',
                'description' => null,
                'is_core' => false,
                'status' => 1,
            ],
        );
    }
}
