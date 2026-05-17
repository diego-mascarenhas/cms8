<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JetstreamTeamRoleSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_replaces_previous_spatie_role_when_jetstream_role_changes(): void
    {
        foreach (['admin', 'editor'] as $roleName)
        {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->assignRole('editor');

        $synchronizer = new JetstreamTeamRoleSynchronizer;
        $synchronizer->sync($user, 'admin');

        $user->refresh();

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
    }
}
