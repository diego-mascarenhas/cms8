<?php

namespace Tests\Feature;

use App\Enums\MultimediaVisibility;
use App\Models\Team;
use App\Models\TeamFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborator_can_create_team_file_with_upload(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');

        $file = UploadedFile::fake()->image('brand-logo.png', 120, 120);

        $response = $this->actingAs($user)->post(route('team-file.store'), [
            'title' => 'Brand guide',
            'description' => 'Internal',
            'visibility' => MultimediaVisibility::PRIVATE->value,
            'file' => $file,
        ]);

        $response->assertRedirect(route('team-file.index'));

        $this->assertDatabaseHas('team_files', [
            'title' => 'Brand guide',
            'team_id' => $user->currentTeam->id,
            'visibility' => MultimediaVisibility::PRIVATE->value,
        ]);

        $teamFile = TeamFile::first();
        $this->assertNotNull($teamFile);
        $this->assertNotNull($teamFile->getFirstMedia('file'));
    }

    public function test_collaborator_can_download_team_file(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');

        $teamFile = TeamFile::factory()->forTeamAndUser($user->currentTeam, $user)->create([
            'visibility' => MultimediaVisibility::PRIVATE,
        ]);

        $teamFile->addMedia(UploadedFile::fake()->image('doc.png', 40, 40))
            ->toMediaCollection('file');

        $response = $this->actingAs($user)->get(route('team-file.download', $teamFile));

        $response->assertOk();
    }

    public function test_client_cannot_access_team_file_index(): void
    {
        $user = $this->createUserWithRole('client');

        $response = $this->actingAs($user)->get(route('team-file.index'));

        $response->assertForbidden();
    }

    public function test_store_rejects_executable_extension(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');

        $file = UploadedFile::fake()->create('setup.exe', 50, 'application/octet-stream');

        $response = $this->actingAs($user)->post(route('team-file.store'), [
            'title' => 'Bad',
            'visibility' => MultimediaVisibility::PRIVATE->value,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('team_files', ['title' => 'Bad']);
    }

    public function test_store_accepts_zip_archive(): void
    {
        Storage::fake('public');

        $user = $this->createUserWithRole('collaborator');

        $file = UploadedFile::fake()->create('assets.zip', 100, 'application/zip');

        $response = $this->actingAs($user)->post(route('team-file.store'), [
            'title' => 'Zip pack',
            'visibility' => MultimediaVisibility::PRIVATE->value,
            'file' => $file,
        ]);

        $response->assertRedirect(route('team-file.index'));
        $this->assertDatabaseHas('team_files', ['title' => 'Zip pack']);
    }

    public function test_developer_destroy_soft_deletes_team_file(): void
    {
        $user = $this->createUserWithRole('developer');

        $teamFile = TeamFile::factory()->forTeamAndUser($user->currentTeam, $user)->create([
            'title' => 'To remove',
        ]);

        $response = $this->actingAs($user)->delete(route('team-file.destroy', $teamFile));

        $response->assertRedirect(route('team-file.index'));
        $this->assertSoftDeleted($teamFile);
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
}
