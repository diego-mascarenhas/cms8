<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\PaidAdCampaign;
use App\Models\User;
use App\Services\PaidAds\PaidAdCampaignCalendarSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppCalendarPaidAdCampaignsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_includes_ads_filter(): void
    {
        $user = $this->calendarUser();

        $this->actingAs($user)
            ->get(route('app-calendar'))
            ->assertOk()
            ->assertSee('id="select-ads"', false)
            ->assertSee('data-value="ads"', false)
            ->assertSee(__('Ads'), false);
    }

    public function test_scheduled_campaign_appears_on_humano_calendar(): void
    {
        $user = $this->calendarUser();
        $team = $user->currentTeam;
        $this->enableTeamModules($team, ['paid_ads']);

        $start = now()->startOfMonth()->addDays(9)->setTime(9, 0);
        $end = $start->copy()->addDays(4);

        $campaign = PaidAdCampaign::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => 'Pauta agosto',
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $response = $this->actingAs($user)->getJson(route('app-calendar-events', [
            'start' => $start->copy()->startOfMonth()->toIso8601String(),
            'end' => $start->copy()->endOfMonth()->toIso8601String(),
        ]));

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'Ads · Pauta agosto',
            ]);

        $this->assertSame(
            PaidAdCampaignCalendarSyncer::LABEL,
            collect($response->json())->firstWhere('title', 'Ads · Pauta agosto')['extendedProps']['calendar'] ?? null,
        );
        $this->assertNotNull($campaign->fresh()->calendar_event_id);
    }

    public function test_calendar_backfills_campaigns_missing_an_event(): void
    {
        $user = $this->calendarUser();
        $team = $user->currentTeam;
        $this->enableTeamModules($team, ['paid_ads']);

        $start = now()->startOfMonth()->addDays(12)->setTime(10, 0);

        $campaign = PaidAdCampaign::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => 'Sin evento',
            'start_at' => $start,
            'end_at' => $start->copy()->addDay(),
        ]);

        $eventId = $campaign->fresh()->calendar_event_id;
        $this->assertNotNull($eventId);

        CalendarEvent::withoutGlobalScopes()->whereKey($eventId)->forceDelete();
        $campaign->forceFill(['calendar_event_id' => null])->saveQuietly();

        $this->actingAs($user)->getJson(route('app-calendar-events', [
            'start' => $start->copy()->startOfMonth()->toIso8601String(),
            'end' => $start->copy()->endOfMonth()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Ads · Sin evento']);

        $this->assertNotNull($campaign->fresh()->calendar_event_id);
    }

    private function calendarUser(): User
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        return $user->refresh();
    }
}
