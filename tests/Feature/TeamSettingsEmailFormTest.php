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
            ->assertDontSee('Outgoing Email (SMTP)', false)
            ->assertDontSee('Incoming Email (IMAP)', false)
            ->assertDontSee('name="email[mail_host]"', false)
            ->assertDontSee('name="email[imap_host]"', false);
    }
}
