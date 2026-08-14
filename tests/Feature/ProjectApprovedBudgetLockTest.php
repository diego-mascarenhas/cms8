<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectApprovedBudgetLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    #[Test]
    public function approved_budget_cannot_open_edit_form(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->get(route('project.edit', $project->id))
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');
    }

    #[Test]
    public function approved_budget_can_change_status_via_modal_endpoint(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertSame(ProjectStatus::STATUS_IN_PROGRESS, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function approved_budget_rejects_disallowed_status(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_BUDGET,
            ])
            ->assertSessionHasErrors('status_id');

        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function in_progress_project_can_move_to_waiting_for_response_but_not_back_to_approved(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->forceFill(['status_id' => ProjectStatus::STATUS_IN_PROGRESS])->save();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_WAITING_FOR_RESPONSE,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertSame(ProjectStatus::STATUS_WAITING_FOR_RESPONSE, (int) $project->fresh()->status_id);

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_APPROVED,
            ])
            ->assertSessionHasErrors('status_id');

        $this->assertSame(ProjectStatus::STATUS_WAITING_FOR_RESPONSE, (int) $project->fresh()->status_id);

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertSame(ProjectStatus::STATUS_IN_PROGRESS, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function approved_budget_show_page_offers_status_modal_not_edit(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee('projectStatusModal', false)
            ->assertSee(__('This approved budget is locked. Only the project status can be changed.'), false)
            ->assertDontSee(__('Locked'), false)
            ->assertDontSee(route('project.edit', $project->id), false);
    }

    #[Test]
    public function approved_budget_cannot_be_saved_via_store_update(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.store'), [
                'id' => $project->id,
                'name' => 'Hacked name',
                'real_name' => 'Hacked real name',
                'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
                'enterprise_id' => $project->enterprise_id,
                'responsible_id' => $project->responsible_id,
                'data' => $project->data,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');

        $this->assertSame('Dashboard Innovación — 4 secciones', $project->fresh()->name);
        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function new_project_can_be_created_with_empty_id_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Client Create',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $response = $this->actingAs($user)
            ->post(route('project.store'), [
                'id' => '',
                'name' => 'New project internal',
                'real_name' => 'New project real',
                'status_id' => ProjectStatus::STATUS_BUDGET,
                'enterprise_id' => $enterprise->id,
                'responsible_id' => $user->id,
                'description' => 'Created via empty id field',
            ]);

        $project = Project::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('name', 'New project internal')
            ->first();

        $this->assertNotNull($project);
        $response->assertRedirect(route('project.show', $project->id));
        $this->assertSame('New project real', $project->real_name);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function createApprovedProject(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Fanyion',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'name' => 'Dashboard Innovación — 4 secciones',
            'real_name' => 'Dashboard Innovación — 4 secciones',
            'data' => [
                'budget_preview_token' => 'lock-test-token',
                'budget_client_response' => [
                    'status' => 'accepted',
                    'accepted_by_name' => 'Cliente',
                    'responded_at' => now()->toIso8601String(),
                ],
                'suggested_tasks' => [
                    [
                        'title' => 'Task',
                        'estimated_hours' => 1,
                        'unit_price' => 120,
                        'included' => true,
                    ],
                ],
            ],
        ]));

        return [$user, $project];
    }
}
