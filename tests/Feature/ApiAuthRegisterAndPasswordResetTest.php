<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiAuthRegisterAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_team_and_token(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'secret12',
        ]);

        $response->assertOk()
            ->assertJsonPath('email', 'ana@example.com')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('current_team.is_owner', true)
            ->assertJsonPath('current_team.apps.assistant.active', true)
            ->assertJsonPath('current_team.apps.assistant.status', 'paid')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'current_team' => ['id', 'name', 'apps']]);

        $user = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->current_team_id);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->can('create', \App\Models\Contact::class));
        $this->assertTrue(Hash::check('secret12', $user->password));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'secret12',
        ])->assertStatus(400)
            ->assertJsonFragment(['El email ya está registrado. Por favor, use otro']);
    }

    public function test_forgot_password_sends_reset_link_to_assistant_url(): void
    {
        Notification::fake();
        config(['services.assistant.url' => 'https://idoneo-assistant.test']);

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
            'frontend_url' => 'https://idoneo-assistant.test',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user)
        {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->actionUrl, 'https://idoneo-assistant.test/reset-password?')
                && str_contains((string) $mail->actionUrl, 'email='.urlencode($user->email))
                && str_contains((string) $mail->actionUrl, 'token=');
        });
    }

    public function test_forgot_password_sends_reset_link_to_mailer_url(): void
    {
        Notification::fake();
        config([
            'services.assistant.url' => 'https://idoneo-assistant.test',
            'services.mailer.url' => 'https://idoneo-mailer.test',
        ]);

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
            'frontend_url' => 'https://idoneo-mailer.test',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user)
        {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->actionUrl, 'https://idoneo-mailer.test/reset-password?')
                && str_contains((string) $mail->actionUrl, 'email='.urlencode($user->email))
                && str_contains((string) $mail->actionUrl, 'token=');
        });
    }

    public function test_forgot_password_ignores_unknown_frontend_url(): void
    {
        Notification::fake();
        config([
            'services.assistant.url' => 'https://idoneo-assistant.test',
            'app.url' => 'https://humano.test',
        ]);

        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
            'frontend_url' => 'https://evil.example',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user)
        {
            $mail = $notification->toMail($user);

            return str_starts_with((string) $mail->actionUrl, 'https://idoneo-assistant.test/reset-password?')
                && ! str_contains((string) $mail->actionUrl, 'evil.example');
        });
    }

    public function test_forgot_password_does_not_reveal_missing_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user)
        {
            $this->postJson('/api/auth/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'New-password-1',
                'password_confirmation' => 'New-password-1',
            ])->assertOk()
                ->assertJsonPath('success', true);

            return true;
        });

        $this->assertTrue(Hash::check('New-password-1', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertStatus(422);
    }
}
