<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamStripeCustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamStripeCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_customer_id_updates_team_stripe_id_for_any_category(): void
    {
        config([
            'stripe_accounts.mailer.secret' => 'sk_mailer_ignored',
            'stripe_accounts.mentoring.secret' => 'sk_mentoring_ignored',
            'cashier.secret' => 'sk_test_default',
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => null,
        ]);

        $service = app(TeamStripeCustomerService::class);
        $service->persistStripeCustomerIdForCategory($team, 'mailer', 'cus_test_default_123');
        $this->assertSame('cus_test_default_123', $team->fresh()->stripe_id);

        $service->persistStripeCustomerIdForCategory($team, 'mentoring', 'cus_test_mentoring_123');
        $this->assertSame('cus_test_mentoring_123', $team->fresh()->stripe_id);
        $this->assertNull($team->fresh()->getSetting('stripe_id_mentoring'));
    }

    public function test_persist_customer_id_updates_team_stripe_id_for_empty_category(): void
    {
        config(['cashier.secret' => 'sk_test_default']);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => null,
        ]);

        app(TeamStripeCustomerService::class)->persistStripeCustomerIdForCategory($team, '', 'cus_test_env_default');

        $this->assertSame('cus_test_env_default', $team->fresh()->stripe_id);
    }

    public function test_forget_persisted_customer_id_clears_team_stripe_id(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_stale',
        ]);

        app(TeamStripeCustomerService::class)->forgetPersistedCustomerId($team);

        $this->assertNull($team->fresh()->stripe_id);
    }

    public function test_persist_customer_id_does_not_steal_another_teams_customer(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $teamA = Team::factory()->create([
            'user_id' => $ownerA->id,
            'stripe_id' => 'cus_owned_by_a',
        ]);
        $teamB = Team::factory()->create([
            'user_id' => $ownerB->id,
            'stripe_id' => null,
        ]);

        app(TeamStripeCustomerService::class)
            ->persistStripeCustomerIdForCategory($teamB, '', 'cus_owned_by_a');

        $this->assertNull($teamB->fresh()->stripe_id);
        $this->assertSame('cus_owned_by_a', $teamA->fresh()->stripe_id);
    }

    public function test_stripe_id_is_unique_across_teams(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        Team::factory()->create([
            'user_id' => $ownerA->id,
            'stripe_id' => 'cus_unique_guard',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Team::factory()->create([
            'user_id' => $ownerB->id,
            'stripe_id' => 'cus_unique_guard',
        ]);
    }

    public function test_get_customer_id_reads_team_stripe_id_even_when_category_setting_exists(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_platform',
        ]);
        $team->setSetting('stripe_id_mailer', 'cus_mailer_legacy');

        $this->assertSame(
            'cus_platform',
            app(TeamStripeCustomerService::class)->getStripeCustomerIdForCategory($team, 'mailer'),
        );
    }
}
