<?php

namespace Tests\Feature\Api;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\BillingAffiliateCommission;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AffiliateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        config(['humano_pricing.affiliate_commission_percent' => 30]);
    }

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function adminWithAffiliatesModule(?array $teamAttrs = null): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        if ($teamAttrs !== null)
        {
            $team->forceFill($teamAttrs)->save();
        }

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Affiliate program',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('affiliates');

        $token = $user->createToken('idoneo-affiliates-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }

    public function test_dashboard_returns_affiliate_payload_for_eligible_team(): void
    {
        [, $team, $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_api_referrer',
            'referred_by' => null,
        ]);

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_api_paying',
            'referred_by' => 'cus_api_referrer',
        ]);

        BillingAffiliateCommission::query()->create([
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $team->id,
            'stripe_invoice_id' => 'in_api_aff_1',
            'amount_paid_cents' => 10000,
            'currency' => 'eur',
            'commission_percent' => 30,
            'commission_amount_cents' => 3000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.referral_code', 'cus_api_referrer')
            ->assertJsonPath('data.commission_percent', 30)
            ->assertJsonPath('data.totals_as_referrer.EUR.commission_cents', 3000);

        $this->assertNotEmpty($response->json('data.commissions_as_referrer'));
    }

    public function test_dashboard_marks_referred_team_as_ineligible(): void
    {
        [, , $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_referred_team',
            'referred_by' => 'cus_someone_else',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.referral_code', null);
    }

    public function test_dashboard_forbidden_without_affiliates_module(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $token = $user->createToken('no-module')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/affiliates/dashboard')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_can_send_affiliate_invitation_via_api(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'starter',
                    'name' => 'Starter',
                    'checkout_url' => 'https://buy.stripe.com/test_starter',
                    'checkout_available' => true,
                ],
            ],
        ]);

        [, $team, $token] = $this->adminWithAffiliatesModule([
            'stripe_id' => 'cus_invite_referrer',
            'referred_by' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/affiliates/invitations', [
                'invite_name' => 'Ana Cliente',
                'invite_email' => 'ana@example.com',
                'invite_plan' => 'starter',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invitee_email', 'ana@example.com');

        $this->assertDatabaseHas('affiliate_invitations', [
            'team_id' => $team->id,
            'invitee_email' => 'ana@example.com',
            'plan_id' => 'starter',
        ]);

        Mail::assertSent(AffiliatePurchaseInvitationMail::class);
    }
}
