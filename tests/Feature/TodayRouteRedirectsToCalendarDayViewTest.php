<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayRouteRedirectsToCalendarDayViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_route_redirects_to_calendar_day_view(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('today'))
            ->assertRedirect(route('app-calendar', ['view' => 'timeGridDay']));
    }
}
