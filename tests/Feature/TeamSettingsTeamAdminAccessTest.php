<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSettingsTeamAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_admin_can_open_and_update_paid_ads_settings(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $teamAdmin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($teamAdmin->id, ['role' => 'admin']);

        $this->enablePaidAdsModule($team);

        $this->actingAs($teamAdmin)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'paid_ads']))
            ->assertOk();

        $this->actingAs($teamAdmin)
            ->put(route('team-settings.update', $team), [
                'paid_ads' => [
                    'paid_ads_meta_app_id' => 'team-admin-meta-id',
                ],
            ])
            ->assertRedirect();

        $this->assertSame('team-admin-meta-id', $team->fresh()->getSetting('paid_ads_meta_app_id'));
    }

    public function test_team_editor_cannot_open_team_settings(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $editor = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($editor->id, ['role' => 'editor']);

        $this->actingAs($editor)
            ->get(route('team-settings.edit', ['team' => $team, 'group' => 'paid_ads']))
            ->assertForbidden();
    }

    private function enablePaidAdsModule(Team $team): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'paid_ads'],
            [
                'name' => 'Paid Ads',
                'icon' => 'target-arrow',
                'description' => 'Paid advertising campaigns',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('paid_ads');
    }
}
