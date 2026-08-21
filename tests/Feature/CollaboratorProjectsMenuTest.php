<?php

namespace Tests\Feature;

use App\Helpers\MenuHelper;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollaboratorProjectsMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        foreach (['projects', 'tasks', 'contacts'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'layout',
                    'description' => 'Test',
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }
    }

    public function test_collaborator_menu_includes_projects_when_team_module_is_disabled(): void
    {
        $collaborator = $this->userWithRole('collaborator');
        $team = $collaborator->currentTeam;
        $team->enableModule('tasks');
        $team->enableModule('contacts');
        $team = $team->fresh('modules');
        $this->assertFalse($team->hasModule('projects'));

        $urls = $this->menuUrlsFor($collaborator, $team);

        $this->assertContains('project/list', $urls);
        $this->assertContains('kanban', $urls);
        $this->assertContains('contact/list', $urls);
    }

    public function test_client_menu_hides_projects_when_team_module_is_disabled(): void
    {
        $client = $this->userWithRole('client');
        $team = $client->currentTeam;
        $this->assertFalse($team->hasModule('projects'));

        $urls = $this->menuUrlsFor($client, $team);

        $this->assertNotContains('project/list', $urls);
    }

    public function test_admin_menu_includes_projects_only_when_team_module_is_enabled(): void
    {
        $admin = $this->userWithRole('admin');
        $team = $admin->currentTeam;
        $this->assertFalse($team->hasModule('projects'));

        $this->assertNotContains('project/list', $this->menuUrlsFor($admin, $team));

        $team->enableModule('projects');
        $team->unsetRelation('modules');

        $this->assertContains('project/list', $this->menuUrlsFor($admin->fresh(), $team->fresh()));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole($roleName);

        if ($roleName !== 'admin')
        {
            $user->teams()->syncWithoutDetaching([
                $team->id => ['role' => $roleName === 'collaborator' ? 'editor' : 'client'],
            ]);
        }

        return $user->fresh(['currentTeam.modules', 'roles']);
    }

    /**
     * @return list<string>
     */
    private function menuUrlsFor(User $user, Team $team): array
    {
        $menu = MenuHelper::filterMenuForUser(MenuHelper::getMenuConfig(), $user, $team);

        return collect($menu['menu'])
            ->pluck('url')
            ->filter()
            ->values()
            ->all();
    }
}
