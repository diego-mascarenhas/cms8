<?php

namespace Tests\Feature;

use App\Mail\ClientLoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_email_is_not_registered(): void
    {
        $this->postJson('/api/client/auth/email', [
            'email' => 'nadie@example.com',
        ])->assertNotFound()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('message', 'Este email no está registrado.');
    }

    public function test_registered_client_can_verify_a_login_code(): void
    {
        Mail::fake();

        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);
        $user->forceFill([
            'current_team_id' => $user->ownedTeams()->first()->id,
        ])->save();

        $this->postJson('/api/client/auth/email', [
            'email' => 'Ana@example.com',
        ])->assertOk()
            ->assertJsonPath('registered', true);

        $code = null;
        Mail::assertSent(ClientLoginCodeMail::class, function (ClientLoginCodeMail $mail) use (&$code)
        {
            $code = $mail->code;

            return $mail->hasTo('ana@example.com');
        });

        $this->postJson('/api/client/auth/verify', [
            'email' => 'ana@example.com',
            'code' => '000000',
        ])->assertStatus(422);

        $this->postJson('/api/client/auth/verify', [
            'email' => 'ana@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.name', 'Ana')
            ->assertJsonStructure(['token']);
    }

    public function test_dashboard_requires_authentication_and_starts_empty(): void
    {
        $this->getJson('/api/client/dashboard')->assertUnauthorized();

        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('idoneo-mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/client/dashboard')
            ->assertOk()
            ->assertJsonPath('invoices', [])
            ->assertJsonPath('services', [])
            ->assertJsonPath('payments', [])
            ->assertJsonPath('leads', []);

        $this->withToken($token)
            ->getJson('/api/client/tickets')
            ->assertOk()
            ->assertJsonPath('tickets', []);

        $this->withToken($token)
            ->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_code_mail_uses_mailpit_locally(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();

        User::factory()->withPersonalTeam()->create([
            'email' => 'ana@example.com',
        ]);

        $this->postJson('/api/client/auth/email', [
            'email' => 'ana@example.com',
        ])->assertOk()
            ->assertJsonPath('registered', true);

        Mail::assertSent(ClientLoginCodeMail::class, function (ClientLoginCodeMail $mail)
        {
            return $mail->mailer === 'mailpit' && $mail->hasTo('ana@example.com');
        });
    }
}
