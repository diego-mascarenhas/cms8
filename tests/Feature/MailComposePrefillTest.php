<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailComposePrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_list_with_valid_compose_query_outputs_prefill_payload(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->get(route('mail-list', [
            'compose' => '1',
            'to' => 'client@example.com',
            'name' => 'Jane Client',
        ]));

        $response->assertOk();
        $response->assertSee('client@example.com', false);
        $response->assertSee('value="client@example.com"', false);
    }

    public function test_mail_list_with_invalid_to_ignores_prefill(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $this->actingAs($user);

        $response = $this->get(route('mail-list', [
            'compose' => '1',
            'to' => 'not-an-email',
        ]));

        $response->assertOk();
        $response->assertDontSee('not-an-email', false);
    }
}
