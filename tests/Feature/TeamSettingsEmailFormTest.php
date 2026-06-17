<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSettingsEmailFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_settings_form_hides_smtp_and_imap_sections(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'email']))
            ->assertOk()
            ->assertSee('name="email[mail_from_name]"', false)
            ->assertSee('name="email[mail_from_address]"', false)
            ->assertSee('name="email[mailer_from_name]"', false)
            ->assertSee('name="email[mailer_from_address]"', false)
            ->assertDontSee('Outgoing Email (SMTP)', false)
            ->assertDontSee('Incoming Email (IMAP)', false)
            ->assertDontSee('name="email[mail_host]"', false)
            ->assertDontSee('name="email[imap_host]"', false);
    }

    public function test_email_settings_form_can_save_custom_mailer_sender(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->put(route('team-settings.update', $team), [
                'email' => [
                    'mail_from_name' => 'Team ACME',
                    'mail_from_address' => 'team@acme.test',
                    'mailer_from_name' => 'Mailer ACME',
                    'mailer_from_address' => 'mailer@acme.test',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', __('app.team_setting_mailer_saved'));

        $team->refresh();
        $this->assertTrue($team->hasTeamEmailSenderConfigured());
        $this->assertTrue($team->hasMailerSenderOverrideConfigured());
        $this->assertSame('Team ACME', $team->getSetting('mail_from_name'));
        $this->assertSame('team@acme.test', $team->getSetting('mail_from_address'));
        $this->assertSame('Mailer ACME', $team->getMailerEmailSender()['from_name']);
        $this->assertSame('mailer@acme.test', $team->getMailerEmailSender()['from_address']);
    }

    public function test_mailer_sender_falls_back_to_team_sender_when_override_incomplete(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->put(route('team-settings.update', $team), [
                'email' => [
                    'mail_from_name' => 'Team ACME',
                    'mail_from_address' => 'team@acme.test',
                    'mailer_from_name' => '',
                    'mailer_from_address' => '',
                ],
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertFalse($team->hasMailerSenderOverrideConfigured());
        $this->assertTrue($team->hasOutgoingEmailSenderConfigured());
        $this->assertSame('Team ACME', $team->getMailerEmailSender()['from_name']);
        $this->assertSame('team@acme.test', $team->getMailerEmailSender()['from_address']);
    }

    public function test_email_settings_form_shows_spanish_copy(): void
    {
        app()->setLocale('es');

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'email']))
            ->assertOk()
            ->assertSee(__('app.team_setting_mailer_email_title'), false)
            ->assertSee(__('app.team_setting_team_sender_title'), false)
            ->assertSee(__('app.team_setting_mailer_sender_title'), false)
            ->assertSee(__('Save Changes'), false)
            ->assertSee(__('Settings').'/', false)
            ->assertDontSee('Save Changes', false)
            ->assertDontSee('Settings/', false);
    }
}
