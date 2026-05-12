<?php

namespace Tests\Feature;

use App\Mail\NewUserNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestWelcomeEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_welcome_mailable_to_given_address(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->artisan('email:test-welcome', [
            'to' => 'preview-welcome-'.uniqid('', true).'@example.com',
            '--user-id' => (string) $user->id,
        ])->assertSuccessful();

        Mail::assertSent(NewUserNotification::class, function (NewUserNotification $mail) use ($user)
        {
            return $mail->user->is($user);
        });
    }
}
