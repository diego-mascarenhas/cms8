<?php

namespace Tests\Feature;

use App\Mail\AffiliatePurchaseInvitationMail;
use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\AffiliateCommissionRecorder;
use App\Services\AffiliateReferralLinkBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingAffiliateTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['humano_pricing.affiliate_commission_percent' => 40]);
    }

    public function test_commission_recorded_when_paying_team_has_referrer_stripe_id(): void
    {
        $referrerOwner = User::factory()->create();
        $referrerTeam = Team::factory()->create([
            'user_id' => $referrerOwner->id,
            'stripe_id' => 'cus_referrer_abc',
        ]);
        $referrerOwner->forceFill(['current_team_id' => $referrerTeam->id])->save();

        $payingOwner = User::factory()->create();
        $payingTeam = Team::factory()->create([
            'user_id' => $payingOwner->id,
            'stripe_id' => 'cus_paying_xyz',
            'referred_by' => 'cus_referrer_abc',
        ]);
        $payingOwner->forceFill(['current_team_id' => $payingTeam->id])->save();

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($payingTeam, [
            'id' => 'in_test_aff_team_1',
            'customer' => 'cus_paying_xyz',
            'amount_paid' => 10000,
            'currency' => 'eur',
        ]);

        $this->assertDatabaseHas('billing_affiliate_commissions', [
            'stripe_invoice_id' => 'in_test_aff_team_1',
            'paying_team_id' => $payingTeam->id,
            'referrer_team_id' => $referrerTeam->id,
            'commission_amount_cents' => 4000,
        ]);
    }

    public function test_no_commission_when_referrer_is_same_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_same_team',
            'referred_by' => 'cus_same_team',
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, [
            'id' => 'in_test_same_team',
            'customer' => 'cus_same_team',
            'amount_paid' => 5000,
            'currency' => 'usd',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());
    }

    public function test_no_commission_without_referred_by(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_no_ref',
            'referred_by' => null,
        ]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        app(AffiliateCommissionRecorder::class)->recordFromInvoice($team, [
            'id' => 'in_test_no_ref',
            'customer' => 'cus_no_ref',
            'amount_paid' => 5000,
            'currency' => 'eur',
        ]);

        $this->assertSame(0, BillingAffiliateCommission::query()->count());
    }

    public function test_referral_link_builder_appends_client_reference_id(): void
    {
        $builder = app(AffiliateReferralLinkBuilder::class);
        $url = $builder->buildLink(
            'https://buy.stripe.com/testlink',
            'cus_ref_123',
            'buyer@example.com',
        );

        $this->assertStringContainsString('client_reference_id=cus_ref_123', $url);
        $this->assertStringContainsString('prefilled_email=buyer%40example.com', $url);
    }

    public function test_affiliate_invite_sends_email(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_invite_ref'])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)->post(route('billing.affiliate-invite'), [
            'invite_name' => 'Jane Doe',
            'invite_email' => 'jane@example.com',
            'invite_plan' => 'assistant',
        ])->assertRedirect(route('billing.index'));

        Mail::assertSent(AffiliatePurchaseInvitationMail::class, function ($mail): bool
        {
            return $mail->hasTo('jane@example.com')
                && $mail->inviteeName === 'Jane Doe'
                && str_contains($mail->checkoutUrl, 'client_reference_id=cus_invite_ref')
                && str_contains($mail->checkoutUrl, 'prefilled_email=jane%40example.com')
                && str_contains($mail->pricingUrl, '/pricing');
        });

        $this->assertDatabaseHas('affiliate_invitations', [
            'team_id' => $team->id,
            'invitee_email' => 'jane@example.com',
            'plan_id' => 'assistant',
        ]);

        $invitation = \App\Models\AffiliateInvitation::query()
            ->where('invitee_email', 'jane@example.com')
            ->first();
        $this->assertNotNull($invitation?->tracking_token);
        $this->assertNotNull($invitation?->sent_at);
    }

    public function test_affiliate_invite_returns_field_validation_errors(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_invite_ref'])->save();
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->from(route('billing.index'))
            ->post(route('billing.affiliate-invite'), [])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHasErrors(['invite_name', 'invite_email', 'invite_plan']);

        $this->get(route('billing.index'))
            ->assertOk()
            ->assertSee('affiliate-invite-form', false)
            ->assertDontSee('var myModal = new bootstrap.Modal(document.getElementById(\'editBillingModal\'))', false);

        Mail::assertNothingSent();
    }

    public function test_affiliate_invite_uses_team_from_when_configured(): void
    {
        Mail::fake();

        config([
            'humano_pricing.plans' => [
                [
                    'id' => 'assistant',
                    'checkout_url' => 'https://buy.stripe.com/test_assistant',
                    'checkout_available' => true,
                ],
            ],
        ]);

        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Carlos Referidor']);
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_from_test'])->save();
        $team->setSetting('mail_from_name', 'Mi Empresa SA', ['group' => 'email', 'type' => 'string']);
        $team->setSetting('mail_from_address', 'referidos@miempresa.test', ['group' => 'email', 'type' => 'string']);
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)->post(route('billing.affiliate-invite'), [
            'invite_name' => 'Ana',
            'invite_email' => 'ana@example.com',
            'invite_plan' => 'assistant',
        ])->assertRedirect(route('billing.index'));

        Mail::assertSent(AffiliatePurchaseInvitationMail::class, 1);

        $mailable = Mail::sent(AffiliatePurchaseInvitationMail::class)->first();
        $this->assertNotNull($mailable);
        $mailable->build();

        $this->assertCount(1, $mailable->from);
        $this->assertSame('referidos@miempresa.test', $mailable->from[0]['address']);
        $this->assertSame('Mi Empresa SA', $mailable->from[0]['name']);
    }

    public function test_billing_index_lists_sent_invitations(): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'affiliates'],
            [
                'name' => 'Affiliates',
                'icon' => 'affiliate',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->enableModule('affiliates');
        $user->forceFill(['current_team_id' => $team->id])->save();

        AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => 'Pedro Test',
            'invitee_email' => 'pedro@example.com',
            'plan_id' => 'assistant',
            'plan_name' => 'Assistant',
        ]);

        $this->actingAs($user)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Invitaciones enviadas', false)
            ->assertSee('Pedro Test', false)
            ->assertSee('pedro@example.com', false)
            ->assertSee('Assistant', false);
    }
}
