<?php

namespace Tests\Feature;

use App\Mail\TeamInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestTeamInvitationEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_team_invitation_mailable_to_given_address(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $to = 'preview-invite-'.uniqid('', true).'@example.com';

        $this->artisan('email:test-team-invitation', [
            'to' => $to,
            '--team-id' => (string) $team->id,
        ])->assertSuccessful();

        Mail::assertSent(TeamInvitation::class, function (TeamInvitation $mail) use ($team, $to): bool
        {
            return $mail->invitation->team->is($team)
                && $mail->invitation->email === $to;
        });
    }

    public function test_command_rejects_invalid_recipient(): void
    {
        Team::factory()->create();

        $this->artisan('email:test-team-invitation', [
            'to' => 'not-an-email',
        ])->assertFailed();
    }
}
