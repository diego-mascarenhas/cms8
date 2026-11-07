<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarEventsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_events_returns_team_events_for_the_requested_range(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        CalendarEvent::query()->create([
            'team_id' => $team->id,
            'title' => 'Reunión de hoy',
            'start' => Carbon::parse('2026-11-07 10:00:00'),
            'end' => Carbon::parse('2026-11-07 11:00:00'),
            'all_day' => false,
            'label' => 'Business',
            'notes' => 'Plan del día',
        ]);

        $token = $user->createToken('calendar')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/calendar/events?start=2026-11-07 00:00:00&end=2026-11-08 00:00:00')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Reunión de hoy',
                'allDay' => false,
            ])
            ->assertJsonPath('0.extendedProps.calendar', 'Business')
            ->assertJsonPath('0.extendedProps.description', 'Plan del día');
    }
}
