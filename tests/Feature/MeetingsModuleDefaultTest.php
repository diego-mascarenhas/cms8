<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingsModuleDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_team_does_not_enable_meetings_by_default(): void
    {
        Module::query()->updateOrCreate(
            ['key' => 'meetings'],
            [
                'name' => 'Meetings',
                'icon' => 'microphone',
                'description' => 'Test',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => true,
        ]);

        $this->assertFalse($team->fresh()->hasModule('meetings'));
    }
}
