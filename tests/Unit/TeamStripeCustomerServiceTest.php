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

    public function test_persist_customer_id_updates_team_stripe_id_for_default_account(): void
    {
        config([
            'stripe_accounts.mailer.secret' => null,
            'cashier.secret' => 'sk_test_default',
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => null,
        ]);

        app(TeamStripeCustomerService::class)->persistStripeCustomerIdForCategory($team, 'mailer', 'cus_test_default_123');

        $this->assertSame('cus_test_default_123', $team->fresh()->stripe_id);
    }

    public function test_persist_customer_id_updates_team_stripe_id_for_empty_category_like_default_cashier(): void
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

    public function test_persist_customer_id_updates_team_setting_for_dedicated_account(): void
    {
        config([
            'stripe_accounts.mentoring.secret' => 'sk_test_mentoring',
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => null,
        ]);

        app(TeamStripeCustomerService::class)->persistStripeCustomerIdForCategory($team, 'mentoring', 'cus_test_mentoring_123');

        $this->assertSame('cus_test_mentoring_123', $team->fresh()->getSetting('stripe_id_mentoring'));
        $this->assertNull($team->fresh()->stripe_id);
    }
}
