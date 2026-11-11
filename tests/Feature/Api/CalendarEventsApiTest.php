<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEvent;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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

    public function test_calendar_events_include_the_day_an_email_campaign_started(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Campaña de noviembre',
            'type_id' => 1,
            'text' => 'Asunto',
            'started_at' => Carbon::parse('2026-11-07 09:30:00', 'Europe/Madrid')->utc(),
        ]);

        Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Campaña de octubre',
            'type_id' => 1,
            'text' => 'Asunto',
            'started_at' => Carbon::parse('2026-10-02 09:30:00', 'Europe/Madrid')->utc(),
        ]);

        Message::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WhatsApp de noviembre',
            'type_id' => 2,
            'text' => 'Hola',
            'started_at' => Carbon::parse('2026-11-07 11:00:00', 'Europe/Madrid')->utc(),
        ]);

        $token = $user->createToken('calendar')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/calendar/events?start=2026-11-07 00:00:00&end=2026-11-08 00:00:00')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Campaña de noviembre',
                'start' => '2026-11-07',
                'allDay' => true,
            ])
            ->assertJsonPath('0.extendedProps.calendar', 'Email Marketing')
            ->assertJsonMissing(['title' => 'Campaña de octubre'])
            ->assertJsonMissing(['title' => 'WhatsApp de noviembre']);
    }

    public function test_a_timed_event_can_be_created_from_the_calendar(): void
    {
        Queue::fake();
        Mail::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $token = $user->createToken('calendar')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/calendar/events', [
                'title' => 'Bloque de trabajo',
                'start' => '2026-11-11T06:00:00.000Z',
                'end' => '2026-11-11T07:00:00.000Z',
                'all_day' => false,
                'label' => 'Business',
            ])
            ->assertCreated()
            ->assertJsonPath('title', 'Bloque de trabajo')
            ->assertJsonPath('allDay', false);

        $this->assertDatabaseHas('calendar_events', [
            'team_id' => $team->id,
            'title' => 'Bloque de trabajo',
            'all_day' => false,
        ]);
    }
}
