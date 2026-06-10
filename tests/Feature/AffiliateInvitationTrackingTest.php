<?php

namespace Tests\Feature;

use App\Models\AffiliateInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateInvitationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_tracking_marks_invitation_as_opened(): void
    {
        $invitation = $this->createInvitation();

        $this->get(route('affiliate-invite.track.open', ['token' => $invitation->tracking_token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $invitation->refresh();
        $this->assertNotNull($invitation->opened_at);
        $this->assertNull($invitation->clicked_at);
    }

    public function test_click_tracking_marks_invitation_and_redirects(): void
    {
        $invitation = $this->createInvitation();
        $targetUrl = 'https://buy.stripe.com/test_checkout';

        $this->get(route('affiliate-invite.track.click', [
            'token' => $invitation->tracking_token,
            'url' => $targetUrl,
            'link' => 'checkout',
        ]))->assertRedirect($targetUrl);

        $invitation->refresh();
        $this->assertNotNull($invitation->opened_at);
        $this->assertNotNull($invitation->clicked_at);
        $this->assertSame('checkout', $invitation->clicked_link);
    }

    public function test_invalid_token_does_not_error_on_open(): void
    {
        $this->get(route('affiliate-invite.track.open', ['token' => 'invalid-token']))
            ->assertOk();
    }

    public function test_status_label_shows_single_progressive_state(): void
    {
        $invitation = $this->createInvitation();
        $this->assertSame('Enviado', $invitation->statusLabel());
        $this->assertSame('bg-label-success', $invitation->statusBadgeClass());

        $invitation->markOpened();
        $invitation->refresh();
        $this->assertSame('Abierto', $invitation->statusLabel());
        $this->assertSame('bg-label-info', $invitation->statusBadgeClass());

        $invitation->markClicked('checkout');
        $invitation->refresh();
        $this->assertSame('Clic · Suscripción', $invitation->statusLabel());
        $this->assertSame('bg-label-primary', $invitation->statusBadgeClass());
    }

    private function createInvitation(): AffiliateInvitation
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        return AffiliateInvitation::query()->create([
            'team_id' => $team->id,
            'invited_by_user_id' => $user->id,
            'invitee_name' => 'Test Invitee',
            'invitee_email' => 'invitee@example.com',
            'plan_id' => 'assistant',
            'plan_name' => 'Assistant',
            'tracking_token' => AffiliateInvitation::generateTrackingToken(),
            'sent_at' => now(),
        ]);
    }
}
