<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleCredentialsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_check_if_team_has_google_credentials()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        // Initially no credentials
        $this->assertFalse(GoogleCredentialsService::hasCredentials($team));

        // Add credentials
        $team->setSetting('analytics_credentials_json', json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'test@test.iam.gserviceaccount.com',
        ]), ['is_encrypted' => true]);

        // Now has credentials
        $this->assertTrue(GoogleCredentialsService::hasCredentials($team));
    }

    public function test_get_calendar_id_returns_primary_by_default()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $calendarId = GoogleCredentialsService::getCalendarId($team);

        $this->assertEquals('primary', $calendarId);
    }

    public function test_get_calendar_id_returns_custom_id_if_set()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $customId = 'custom-calendar@group.calendar.google.com';
        $team->setSetting('google_calendar_id', $customId);

        $calendarId = GoogleCredentialsService::getCalendarId($team);

        $this->assertEquals($customId, $calendarId);
    }

    public function test_get_calendar_id_extracts_from_credentials()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $serviceEmail = 'test-service@test.iam.gserviceaccount.com';

        $team->setSetting('analytics_credentials_json', json_encode([
            'type' => 'service_account',
            'client_email' => $serviceEmail,
        ]), ['is_encrypted' => true]);

        $calendarId = GoogleCredentialsService::getCalendarId($team);

        $this->assertEquals($serviceEmail, $calendarId);
    }

    public function test_team_settings_page_shows_google_calendar_field()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user);

        $response = $this->get(route('team-settings.edit', ['team' => $team, 'group' => 'calendar']));

        $response->assertStatus(200);
        $response->assertSee(__('team_settings.groups.calendar.title'), false);
        $response->assertSee(__('team_settings.fields.google_calendar_id.label'), false);
    }

    public function test_can_save_google_calendar_id_in_team_settings()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user);

        $calendarId = 'my-calendar@group.calendar.google.com';

        $response = $this->put(route('team-settings.update', $team), [
            '_token' => csrf_token(),
            'calendar' => [
                'google_calendar_id' => $calendarId,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals($calendarId, $team->fresh()->getSetting('google_calendar_id'));
    }
}
