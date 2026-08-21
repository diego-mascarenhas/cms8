<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountManagementPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_can_change_account_owner_password(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $root = User::factory()->create();
        $root->assignRole('root');

        $account = Account::query()->create([
            'name' => 'Cuenta Test',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $this->actingAs($root)
            ->postJson(route('account.update-password', $account->id), [
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $owner->refresh();
        $this->assertTrue(Hash::check('NuevaClave1', $owner->password));
        $this->assertFalse(Hash::check('password', $owner->password));
    }

    public function test_password_change_requires_confirmation(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $root = User::factory()->create();
        $root->assignRole('root');

        $account = Account::query()->create([
            'name' => 'Cuenta Test',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $this->actingAs($root)
            ->postJson(route('account.update-password', $account->id), [
                'password' => 'NuevaClave1',
                'password_confirmation' => 'OtraClave1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_fails_when_account_has_no_owner(): void
    {
        Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);

        $orphanOwner = User::factory()->create();
        $root = User::factory()->create();
        $root->assignRole('root');

        $account = Account::query()->create([
            'name' => 'Huérfana',
            'user_id' => $orphanOwner->id,
            'personal_team' => false,
        ]);
        $orphanOwner->delete();

        $this->actingAs($root)
            ->postJson(route('account.update-password', $account->id), [
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_non_root_cannot_change_account_owner_password(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $account = Account::query()->create([
            'name' => 'Cuenta Test',
            'user_id' => $owner->id,
            'personal_team' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('account.update-password', $account->id), [
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ])
            ->assertForbidden();
    }
}
