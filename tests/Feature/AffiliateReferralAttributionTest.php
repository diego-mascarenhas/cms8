<?php

namespace Tests\Feature;

use App\Models\AffiliateInvitation;
use App\Models\Team;
use App\Models\User;
use App\Services\AffiliateReferralAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateReferralAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('humano_pricing.plans')))
        {
            config(['humano_pricing' => require config_path('humano_pricing.php')]);
        }
    }

    public function test_capture_route_stores_session_and_redirects_to_stripe(): void
    {
        $referrerStripeId = 'cus_capture_test';
        Team::factory()->create(['stripe_id' => $referrerStripeId]);
        $stripeUrl = 'https://buy.stripe.com/test_abc?client_reference_id='.$referrerStripeId;

        $this->get(route('affiliate.referral.capture', [
            'ref' => $referrerStripeId,
            'url' => $stripeUrl,
        ]))
            ->assertRedirect($stripeUrl)
            ->assertSessionHas(AffiliateReferralAttributionService::SESSION_REFERRER_KEY, $referrerStripeId)
            ->assertCookie(config('humano_pricing.affiliate_referral_cookie_name', 'humano_affiliate_ref'), $referrerStripeId);
    }

    public function test_pricing_page_appends_referral_to_checkout_links_from_cookie(): void
    {
        $referrerStripeId = 'cus_pricing_cookie';
        Team::factory()->create(['stripe_id' => $referrerStripeId]);

        $response = $this->withCookie(
            config('humano_pricing.affiliate_referral_cookie_name', 'humano_affiliate_ref'),
            $referrerStripeId,
        )->get(route('pricing'));

        $response->assertOk();
        $response->assertSee('client_reference_id='.$referrerStripeId, false);
    }

    public function test_pricing_page_captures_ref_query_parameter(): void
    {
        $referrerStripeId = 'cus_pricing_query';
        Team::factory()->create(['stripe_id' => $referrerStripeId]);

        $this->get(route('pricing', ['ref' => $referrerStripeId]))
            ->assertOk()
            ->assertSessionHas(AffiliateReferralAttributionService::SESSION_REFERRER_KEY, $referrerStripeId)
            ->assertSee('client_reference_id='.$referrerStripeId, false);
    }

    public function test_resolve_referrer_from_invitation_email(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'stripe_id' => 'cus_invite_email_ref',
        ]);

        AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => 'Buyer',
            'invitee_email' => 'buyer@example.com',
            'plan_id' => 'assistant',
            'plan_name' => 'Assistant',
            'sent_at' => now(),
        ]);

        $resolved = app(AffiliateReferralAttributionService::class)
            ->resolveReferrerFromInvitationEmail('buyer@example.com');

        $this->assertSame('cus_invite_email_ref', $resolved);
    }
}
