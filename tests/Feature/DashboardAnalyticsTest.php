<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Analytics\Facades\Analytics;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_show_analytics_chart_when_team_has_no_analytics(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('analyticsChart', false);
        $response->assertSee(__('Recent contact activity'), false);
    }

    public function test_dashboard_hides_ongoing_projects_card_when_projects_module_disabled(): void
    {
        Module::query()->create([
            'name' => 'Projects',
            'key' => 'projects',
            'icon' => 'folder',
            'description' => 'Test',
            'is_core' => true,
            'status' => 1,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(__('Ongoing Projects'), false);
    }

    public function test_dashboard_shows_analytics_chart_when_team_has_analytics_configured(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $team->setSetting('analytics_property_id', '123456789', ['group' => 'analytics', 'is_encrypted' => false]);
        $team->setSetting('analytics_credentials_json', json_encode(['type' => 'service_account']), ['group' => 'analytics', 'is_encrypted' => true]);

        $fakeData = collect([
            [
                'date' => Carbon::today()->subDays(2),
                'activeUsers' => 10,
                'screenPageViews' => 25,
            ],
            [
                'date' => Carbon::today()->subDays(1),
                'activeUsers' => 15,
                'screenPageViews' => 30,
            ],
        ]);
        Analytics::fake($fakeData);

        $this->actingAs($user);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('analyticsChart', false);
    }

    public function test_team_settings_analytics_group_can_be_edited(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $response = $this->get(route('team-settings.edit', ['team' => $team, 'group' => 'analytics']));

        $response->assertStatus(200);
        $response->assertSee('Google Analytics', false);
        $response->assertSee('GA4 Property ID', false);
        $response->assertSee('Service account credentials', false);
    }

    public function test_team_settings_analytics_can_be_saved(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);

        $response = $this->put(route('team-settings.update', $team), [
            '_token' => csrf_token(),
            '_method' => 'PUT',
            'analytics' => [
                'analytics_property_id' => '987654321',
                'analytics_credentials_json' => json_encode(['type' => 'service_account', 'project_id' => 'test']),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('987654321', $team->fresh()->getSetting('analytics_property_id'));
        $this->assertNotEmpty($team->fresh()->getSetting('analytics_credentials_json'));
    }
}
