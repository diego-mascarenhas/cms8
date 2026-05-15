<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppCalendarEventTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_preserves_utc_instant_from_iso8601_payload(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $startIso = '2026-05-15T14:42:00.000Z';
        $endIso = '2026-05-15T16:00:00.000Z';

        $response = $this->actingAs($user)->postJson(route('app-calendar-events-store'), [
            'title' => 'Design Review',
            'start' => $startIso,
            'end' => $endIso,
            'all_day' => false,
        ]);

        $response->assertCreated();

        $event = CalendarEvent::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->start->utc()->equalTo(Carbon::parse($startIso)->utc()));
        $this->assertTrue($event->end->utc()->equalTo(Carbon::parse($endIso)->utc()));

        $response->assertJsonPath('start', $event->start->toIso8601String());
        $response->assertJsonPath('end', $event->end->toIso8601String());
    }

    public function test_update_preserves_utc_instant_from_iso8601_payload(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user);

        $event = CalendarEvent::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'title' => 'Original',
            'start' => Carbon::parse('2026-05-15T10:00:00', 'UTC'),
            'end' => Carbon::parse('2026-05-15T11:00:00', 'UTC'),
            'all_day' => false,
        ]);

        $startIso = '2026-05-15T14:42:00.000Z';
        $endIso = '2026-05-15T16:00:00.000Z';

        $response = $this->putJson(route('app-calendar-events-update', $event), [
            'start' => $startIso,
            'end' => $endIso,
        ]);

        $response->assertOk();

        $event->refresh();
        $this->assertTrue($event->start->utc()->equalTo(Carbon::parse($startIso)->utc()));
        $this->assertTrue($event->end->utc()->equalTo(Carbon::parse($endIso)->utc()));
    }
}
