<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactListToolbarFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_list_includes_filter_persistence_and_select2_sync(): void
    {
        $user = $this->createAdminWithContactListPermission();

        $response = $this->actingAs($user)->get(route('contact-list'));

        $response->assertOk();
        $response->assertSee('id="EmotionalState"', false);
        $response->assertSee('id="IntentFilter"', false);
        $response->assertSee('id="CategoryFilter"', false);
        $response->assertSee('contact_list_emotional_state', false);
        $response->assertSee('contact_list_intent', false);
        $response->assertSee('contact_list_category', false);
        $response->assertSee('change.select2', false);
        $response->assertSee('change.contactFilter', false);
    }

    private function createAdminWithContactListPermission(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole('admin');

        Permission::firstOrCreate(['name' => 'contact.index', 'guard_name' => 'web']);
        $user->givePermissionTo('contact.index');

        return $user->refresh();
    }
}
