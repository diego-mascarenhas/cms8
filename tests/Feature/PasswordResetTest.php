<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification)
        {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_reset_sets_email_verified_at_when_user_was_unverified(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->assertNull($user->fresh()->email_verified_at);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user)
        {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'New-password-1',
                'password_confirmation' => 'New-password-1',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_preserves_email_verified_at_when_already_verified(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();
        $verifiedAt = $user->fresh()->email_verified_at;
        $this->assertNotNull($verifiedAt);

        $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user)
        {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Another-new-pass-2',
                'password_confirmation' => 'Another-new-pass-2',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue($user->fresh()->email_verified_at->equalTo($verifiedAt));
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        if (! Features::enabled(Features::resetPasswords()))
        {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user)
        {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }
}
