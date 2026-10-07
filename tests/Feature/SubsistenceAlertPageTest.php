<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubsistenceAlertPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_other_teams_do_not_see_the_subsistence_banners(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('strategy.analysis'))
            ->assertOk()
            ->assertDontSee(__('app.subsistence_title'), false);
    }

    public function test_analysis_shows_the_call_minimum_before_the_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00', 'UTC'));
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        config(['organization.revision_alpha_team_id' => $team->id]);

        $this->actingAs($user)
            ->get(route('strategy.analysis'))
            ->assertOk()
            ->assertSee(__('app.subsistence_title'), false)
            ->assertSee(__('app.subsistence_calls_title_warning'), false)
            ->assertSee(__('app.subsistence_calls_pending'), false)
            ->assertSee('19:00', false)
            ->assertSee('Leticia', false)
            ->assertSee('alert-warning', false)
            ->assertDontSee('alert-danger', false);
    }
}
