<?php

namespace Tests\Unit;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_calendar_event_for_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;

        $this->actingAs($user);

        $event = CalendarEvent::create([
            'team_id' => $team->id,
            'title' => 'Test meeting',
            'start' => Carbon::parse('2026-03-16 10:00:00'),
            'end' => Carbon::parse('2026-03-16 11:00:00'),
            'all_day' => false,
            'description' => 'Planning',
            'location' => 'Online',
            'label' => 'Business',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'team_id' => $team->id,
            'title' => 'Test meeting',
        ]);
    }
}
