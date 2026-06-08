<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\MessageTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageEmailSenderBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            MessageTypeSeeder::class,
        ]);
    }

    private function userWithPersonalTeamResolved(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_message_create_shows_email_sender_banner_when_not_configured(): void
    {
        $user = $this->userWithPersonalTeamResolved();

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('id="email-sender-config-banner"', $html);
        $this->assertStringContainsString(e(__('app.email_sender_banner_title')), $html);
        $this->assertStringContainsString('id="emailSenderConfigModal"', $html);
        $this->assertStringContainsString('emailSenderConfigForm', $html);
    }

    public function test_message_create_hides_email_sender_banner_when_configured(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $team = $user->currentTeam;
        $this->assertNotNull($team);
        $team->setSetting('mail_from_name', 'News Sender');
        $team->setSetting('mail_from_address', 'news@example.test');

        $response = $this->actingAs($user)->get(route('message.create'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringNotContainsString('id="email-sender-config-banner"', $html);
    }

    public function test_clearing_mail_from_address_in_team_settings_shows_banner_on_message_create(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $team = $user->currentTeam;
        $this->assertNotNull($team);
        $team->setSetting('mail_from_name', 'News Sender');
        $team->setSetting('mail_from_address', 'news@example.test');

        $this->actingAs($user)->put(route('team-settings.update', $team), [
            'email' => [
                'mail_from_name' => 'News Sender',
                'mail_from_address' => '',
            ],
        ])->assertRedirect();

        $team->refresh();
        $this->assertFalse($team->hasOutgoingEmailSenderConfigured());
        $this->assertNull($team->getSetting('mail_from_address'));

        $response = $this->actingAs($user)->get(route('message.create'));
        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('id="email-sender-config-banner"', $html);
    }

    public function test_update_email_sender_endpoint_persists_team_settings(): void
    {
        $user = $this->userWithPersonalTeamResolved();
        $team = $user->currentTeam;
        $this->assertNotNull($team);

        $response = $this->actingAs($user)->putJson(route('team-settings.update-email-sender', $team), [
            'mail_from_name' => 'Campaign Sender',
            'mail_from_address' => 'campaign@example.test',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('sender.from_name', 'Campaign Sender');
        $response->assertJsonPath('sender.from_address', 'campaign@example.test');

        $team->refresh();
        $this->assertTrue($team->hasOutgoingEmailSenderConfigured());
        $this->assertSame('Campaign Sender', $team->getSetting('mail_from_name'));
        $this->assertSame('campaign@example.test', $team->getSetting('mail_from_address'));
    }
}
