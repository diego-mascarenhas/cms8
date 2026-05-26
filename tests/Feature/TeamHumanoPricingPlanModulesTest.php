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
            config('humano_pricing.plan_team_modules.mentor', []),
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
        $this->assertFalse($team->hasModule('dashboard'));
        $this->assertFalse($team->hasModule('clients'));
        $this->assertTrue($team->hasModule('today'));
        $this->assertTrue($team->hasModule('prompts'));
        $this->assertFalse($team->hasModule('mailer'));
        $this->assertFalse($team->hasModule('landings'));
        $this->assertTrue($team->hasModule('chat'));
    }

    public function test_hunter_plan_includes_mailer_and_landings(): void
    {
        $this->seedModulesFromPricingConfig();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'hunter');

        $team = $team->fresh();
        $this->assertTrue($team->hasModule('chat'));
        $this->assertTrue($team->hasModule('mailer'));
        $this->assertTrue($team->hasModule('landings'));
        $this->assertFalse($team->hasModule('funnel'));
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

    public function test_demo_team_plan_slug_defaults_to_assistant(): void
    {
        $this->assertSame('assistant', config('humano_pricing.demo_team_plan_slug'));
        $this->assertSame(
            config('humano_pricing.plan_team_modules.assistant'),
            config('humano_pricing.plan_team_modules.'.config('humano_pricing.demo_team_plan_slug')),
        );
    }

    public function test_assistant_demo_plan_excludes_commerce_and_contents_modules(): void
    {
        $keys = config('humano_pricing.plan_team_modules.assistant', []);

        foreach (['products', 'stores', 'orders', 'contents', 'clients'] as $excluded)
        {
            $this->assertNotContains($excluded, $keys, "Assistant demo plan must not include «{$excluded}».");
        }
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

    public function test_humano_pricing_mentor_bundle_includes_business_and_enterprise_keys(): void
    {
        $keys = config('humano_pricing.plan_team_modules.mentor', []);
        $this->assertSame([], array_diff([
            'settings',
            'campaigns',
            'financial',
            'enterprises',
            'integrations',
            'team_files',
            'tickets',
            'products',
            'orders',
        ], $keys));
    }

    public function test_mentor_plan_enables_business_and_enterprise_modules(): void
    {
        $this->seedModulesFromPricingConfig();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'mentor');

        $team = $team->fresh();
        $this->assertTrue($team->hasModule('funnel'));
        $this->assertTrue($team->hasModule('financial'));
        $this->assertTrue($team->hasModule('enterprises'));
        $this->assertTrue($team->hasModule('integrations'));
        $this->assertTrue($team->hasModule('projects'));
    }

    public function test_mentor_plan_sync_disables_modules_outside_bundle(): void
    {
        $this->seedModulesFromPricingConfig();

        foreach (['list60', 'survival', 'canary_tokens'] as $key)
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
        $this->assertTrue($team->enableModule('survival'));

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, 'mentor');

        $team = $team->fresh();
        $this->assertFalse($team->hasModule('list60'));
        $this->assertFalse($team->hasModule('survival'));
        $this->assertFalse($team->hasModule('canary_tokens'));
        $this->assertTrue($team->hasModule('orders'));
    }
}
