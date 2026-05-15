<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppCalendarDatePickerUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_event_form_renders_kanban_style_date_pickers(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $this->actingAs($user)
            ->get(route('app-calendar'))
            ->assertOk()
            ->assertSee('id="event-start-date-settings"', false)
            ->assertSee('id="event-end-date-settings"', false)
            ->assertSee(__('Selecciona una fecha'), false)
            ->assertSee('ti-calendar', false);
    }
}
