<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPolicyEnterpriseCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_user_can_create_enterprises(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('root');

        $this->assertTrue($user->can('create', Enterprise::class));
    }

    public function test_collaborator_can_create_enterprises(): void
    {
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('collaborator');

        $this->assertTrue($user->can('create', Enterprise::class));
    }
}
