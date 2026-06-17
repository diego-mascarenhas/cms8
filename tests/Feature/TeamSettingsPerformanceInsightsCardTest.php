<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSettingsPerformanceInsightsCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_insights_card_shows_configure_when_module_disabled(): void
    {
        Module::query()->create([
            'name' => 'Team performance insights',
            'key' => 'performance_insights',
            'icon' => 'chart-infographic',
            'description' => 'Daily performance insights',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($owner)->get(route('team-settings.index', $team));

        $response->assertOk();
        $response->assertSee('Team performance insights', false);
        $response->assertSee('data-module="performance_insights"', false);
        $response->assertSee(route('team-settings.edit', ['team' => $team, 'group' => 'notifications']), false);
        $response->assertDontSee(__('Inactive'), false);
    }

    public function test_notifications_settings_show_performance_insights_when_module_disabled(): void
    {
        Module::query()->create([
            'name' => 'Team performance insights',
            'key' => 'performance_insights',
            'icon' => 'chart-infographic',
            'description' => 'Daily performance insights',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($owner)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'notifications']))
            ->assertOk()
            ->assertSee(__('app.team_setting_performance_insights_in_app_notification'), false)
            ->assertSee('name="notifications[performance_insights_in_app_notification]"', false);
    }

    public function test_performance_insights_card_is_active_when_module_enabled(): void
    {
        Module::query()->create([
            'name' => 'Team performance insights',
            'key' => 'performance_insights',
            'icon' => 'chart-infographic',
            'description' => 'Daily performance insights',
            'is_core' => false,
            'status' => 1,
        ]);

        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $team->enableModule('performance_insights');

        $response = $this->actingAs($owner)->get(route('team-settings.index', $team));

        $response->assertOk();
        $response->assertSee('Team performance insights', false);
        $response->assertSee(route('team-settings.edit', ['team' => $team, 'group' => 'notifications']), false);
        $this->assertPerformanceInsightsCardIsNotGrayedOut($response->getContent());
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="[^"]*card[^"]*h-100[^"]*"[^>]*data-module="performance_insights"|<div[^>]*data-module="performance_insights"[^>]*class="[^"]*card[^"]*h-100[^"]*"/',
            $response->getContent(),
        );
    }

    private function assertPerformanceInsightsCardIsNotGrayedOut(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<div[^>]*team-settings-card--disabled[^>]*data-module="performance_insights"|<div[^>]*data-module="performance_insights"[^>]*team-settings-card--disabled/',
            $html,
        );
    }
}
