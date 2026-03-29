<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleCategoriesSelectListingFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_list_category_filter_does_not_embed_categories_manager(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get(route('contact-list'));

        $response->assertStatus(200);
        $this->assertStringNotContainsString(
            'module-cat-mgr-CategoryFilter-contacts',
            $response->getContent(),
        );
        $this->assertStringNotContainsString(
            'title="Manage categories"',
            $response->getContent(),
        );
    }

    public function test_team_file_index_category_filter_does_not_embed_categories_manager(): void
    {
        $user = $this->createCollaboratorWithTeamFilesModule();

        $response = $this->actingAs($user)->get(route('team-file.index'));

        $response->assertStatus(200);
        $this->assertStringNotContainsString(
            'module-cat-mgr-filter_team_file_category-team_files',
            $response->getContent(),
        );
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    private function createCollaboratorWithTeamFilesModule(): User
    {
        $user = $this->createUserWithRole('collaborator');
        $team = $user->currentTeam;

        Module::query()->firstOrCreate(
            ['key' => 'team_files'],
            [
                'name' => 'Team files',
                'icon' => 'folders',
                'description' => 'Team company files',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('team_files');

        return $user->refresh();
    }
}
