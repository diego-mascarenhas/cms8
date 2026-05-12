<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Services\HumanoPricingPlanResolver;
use App\Services\TeamModulesByPricingPlanSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamHumanoPricingPlanModulesTest extends TestCase
{
    use RefreshDatabase;

    private function seedModulesFromPricingConfig(): void
    {
        $keys = array_values(array_unique(array_merge(
            config('humano_pricing.plan_team_modules.assistant', []),
            config('humano_pricing.plan_team_modules.business', []),
        )));

        foreach ($keys as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst(str_replace('-', ' ', $key)),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }
    }

    public function test_resolver_matches_configured_stripe_product_id(): void
    {
        config(['humano_pricing.plans' => [
            [
                'id' => 'assistant',
                'stripe_product_id' => 'prod_test_assistant_only',
            ],
        ]]);

        $resolver = app(HumanoPricingPlanResolver::class);
        $this->assertSame('assistant', $resolver->resolvePlanSlugFromStripeProductId('prod_test_assistant_only'));
        $this->assertNull($resolver->resolvePlanSlugFromStripeProductId('prod_unknown'));
    }

    public function test_assistant_plan_disables_billing_addons_not_in_bundle(): void
    {
        $this->seedModulesFromPricingConfig();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->assertTrue($team->enableModule('invoices'));
        $this->assertTrue($team->enableModule('funnel'));
        $this->assertTrue($team->hasModule('invoices'));
        $this->assertTrue($team->hasModule('funnel'));

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'assistant');

        $team = $team->fresh();
        $this->assertFalse($team->hasModule('invoices'));
        $this->assertFalse($team->hasModule('funnel'));
        $this->assertTrue($team->hasModule('dashboard'));
        $this->assertTrue($team->hasModule('mailer'));
    }

    public function test_business_plan_enables_assistant_and_addon_modules(): void
    {
        $this->seedModulesFromPricingConfig();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'business');

        $team = $team->fresh();
        $this->assertTrue($team->hasModule('dashboard'));
        $this->assertTrue($team->hasModule('mailer'));
        $this->assertTrue($team->hasModule('funnel'));
        $this->assertTrue($team->hasModule('invoices'));
        $this->assertTrue($team->hasModule('payments'));
        $this->assertTrue($team->hasModule('financial'));
    }

    public function test_humano_pricing_business_bundle_includes_expected_addon_keys(): void
    {
        $keys = config('humano_pricing.plan_team_modules.business', []);
        $this->assertSame([], array_diff([
            'settings',
            'campaigns',
            'mailer',
            'funnel',
            'payments',
            'financial',
        ], $keys));
    }

    public function test_demo_team_plan_slug_is_configured(): void
    {
        $slug = config('humano_pricing.demo_team_plan_slug');
        $this->assertContains($slug, ['assistant', 'business', 'foundation']);
    }

    public function test_business_plan_sync_disables_modules_outside_bundle(): void
    {
        $this->seedModulesFromPricingConfig();

        foreach (['list60', 'products', 'projects'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $key,
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->assertTrue($team->enableModule('list60'));
        $this->assertTrue($team->enableModule('products'));
        $this->assertTrue($team->enableModule('projects'));

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'business');

        $team = $team->fresh();
        $this->assertFalse($team->hasModule('list60'));
        $this->assertFalse($team->hasModule('products'));
        $this->assertFalse($team->hasModule('projects'));
        $this->assertTrue($team->hasModule('campaigns'));
    }

    public function test_foundation_plan_uses_business_module_bundle(): void
    {
        $this->seedModulesFromPricingConfig();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'foundation');

        $team = $team->fresh();
        $this->assertTrue($team->hasModule('funnel'));
        $this->assertTrue($team->hasModule('financial'));
    }
}
