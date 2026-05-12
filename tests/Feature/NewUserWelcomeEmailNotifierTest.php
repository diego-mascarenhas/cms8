<?php

namespace Tests\Feature;

use App\Jobs\SendNewUserWelcomeEmail;
use App\Listeners\SendNewUserWelcomeEmailListener;
use App\Models\User;
use App\Support\NewUserWelcomeEmailNotifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NewUserWelcomeEmailNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifier_dispatches_welcome_job_for_real_email(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'email' => 'welcome-test-'.uniqid('', true).'@example.com',
        ]);

        NewUserWelcomeEmailNotifier::queue($user, null);

        Bus::assertDispatched(SendNewUserWelcomeEmail::class, function (SendNewUserWelcomeEmail $job) use ($user)
        {
            return $job->user->id === $user->id;
        });
    }

    public function test_notifier_skips_placeholder_inbox_email(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'email' => 'wa-123456789@chat.placeholder',
        ]);

        NewUserWelcomeEmailNotifier::queue($user, null);

        Bus::assertNotDispatched(SendNewUserWelcomeEmail::class);
    }

    public function test_registered_listener_queues_welcome_for_user_with_team(): void
    {
        Bus::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $this->assertNotNull($team);
        $user->forceFill(['current_team_id' => $team->id])->save();

        app(SendNewUserWelcomeEmailListener::class)->handle(new Registered($user->fresh()));

        Bus::assertDispatched(SendNewUserWelcomeEmail::class);
    }
}
