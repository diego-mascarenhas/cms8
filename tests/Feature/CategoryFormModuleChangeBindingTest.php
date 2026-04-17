<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryFormModuleChangeBindingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam ?? $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_category_form_binds_module_change_with_jquery_for_select2(): void
    {
        $user = $this->actingAdmin();

        $response = $this->actingAs($user)->get(route('categories.create'));

        $response->assertOk();
        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('$(\'#module_id\').on(\'change\', function () {', $html);
        $this->assertStringContainsString('toggleModuleOptions();', $html);
    }
}
