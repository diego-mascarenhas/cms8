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
    public function approved_budget_edit_form_keeps_status_fixed(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->get(route('project.edit', $project->id))
            ->assertOk()
            ->assertSee('name="status_id" value="'.$project->status_id.'"', false)
            ->assertSee(__('Saving keeps the current status. Correct the amount in the breakdown.'), false)
            ->assertDontSee('name="status_id" class="select2', false);
    }

    #[Test]
    public function invoiced_project_rejects_status_changes(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->forceFill(['status_id' => ProjectStatus::STATUS_INVOICED])->save();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_FINISHED,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');

        $this->assertSame(ProjectStatus::STATUS_INVOICED, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function project_form_lists_complimentary_status(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->forceFill([
            'status_id' => ProjectStatus::STATUS_BUDGET,
            'data' => [],
        ])->save();

        $this->actingAs($user)
            ->get(route('project.edit', $project->id))
            ->assertOk()
            ->assertSee(__('project_status.BONIFIED'), false);

        $this->assertDatabaseHas('project_statuses', [
            'id' => ProjectStatus::STATUS_BONIFIED,
            'name' => 'BONIFIED',
        ]);
    }

    #[Test]
    public function approved_budget_can_be_marked_complimentary(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_BONIFIED,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertSame(ProjectStatus::STATUS_BONIFIED, (int) $project->fresh()->status_id);
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
    public function to_invoice_status_survives_show_page_when_budget_was_accepted(): void
    {
        [$user, $project] = $this->createApprovedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->patch(route('project.update-status', $project->id), [
                'status_id' => ProjectStatus::STATUS_TO_INVOICE,
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $this->assertSame(ProjectStatus::STATUS_TO_INVOICE, (int) $project->fresh()->status_id);

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee(__('project_status.TO_INVOICE'), false);

        $this->assertSame(ProjectStatus::STATUS_TO_INVOICE, (int) $project->fresh()->status_id);
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
            ->assertSee('id="locked-status-id" name="status_id" class="select2 form-select"', false)
            ->assertSee(__('This approved budget is locked. Only the project status can be changed.'), false)
            ->assertDontSee(__('Locked'), false)
            ->assertSee(route('project.edit', $project->id), false)
            ->assertDontSee(__('No linked services'), false)
            ->assertDontSee('id="serviceModal"', false);
    }

    #[Test]
    public function approved_budget_amount_can_be_corrected_without_changing_status(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->forceFill([
            'data' => array_merge($project->data, [
                'deposit_invoice' => ['stripe_invoice_id' => 'in_lock_test'],
            ]),
        ])->save();

        $tasks = $project->fresh()->data['suggested_tasks'];
        $tasks[0]['unit_price'] = 150;

        $this->actingAs($user)
            ->from(route('project.edit', $project->id))
            ->post(route('project.store'), [
                'id' => $project->id,
                'name' => $project->name,
                'real_name' => $project->real_name,
                'status_id' => ProjectStatus::STATUS_IN_PROGRESS,
                'enterprise_id' => $project->enterprise_id,
                'responsible_id' => $project->responsible_id,
                'data' => [
                    'suggested_tasks' => json_encode($tasks),
                    'budget_client_response' => ['status' => 'wiped'],
                    'budget_preview_token' => 'replaced-token',
                ],
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $fresh = $project->fresh();
        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $fresh->status_id);
        $this->assertSame(150.0, (float) $fresh->data['suggested_tasks'][0]['unit_price']);
        $this->assertSame('accepted', $fresh->data['budget_client_response']['status']);
        $this->assertSame('lock-test-token', $fresh->data['budget_preview_token']);
        $this->assertSame('in_lock_test', $fresh->data['deposit_invoice']['stripe_invoice_id']);
    }

    #[Test]
    public function invoiced_project_cannot_be_edited_or_receive_board_tasks(): void
    {
        [$user, $project] = $this->createApprovedProject();
        $project->forceFill(['status_id' => ProjectStatus::STATUS_INVOICED])->save();

        $this->actingAs($user)
            ->get(route('project.edit', $project->id))
            ->assertRedirect('/misc-not-authorized');

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertDontSee(route('project.edit', $project->id), false)
            ->assertDontSee(route('project.add-suggested-task', $project->id), false);

        $this->actingAs($user)
            ->post(route('project.add-suggested-task', $project->id), [
                'title' => 'Extra',
                'responsible_id' => $user->id,
            ])
            ->assertRedirect('/misc-not-authorized');
    }

    #[Test]
    public function in_progress_quote_stays_fixed_and_only_the_team_owner_can_edit(): void
    {
        [$owner, $project] = $this->createApprovedProject();
        $project->forceFill(['status_id' => ProjectStatus::STATUS_IN_PROGRESS])->save();

        $member = User::factory()->create();
        $member->assignRole('admin');
        $project->team->users()->attach($member, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $project->team_id])->save();

        $this->actingAs($member)
            ->get(route('project.edit', $project->id))
            ->assertRedirect('/misc-not-authorized');

        $tasks = $project->fresh()->data['suggested_tasks'];
        $tasks[0]['unit_price'] = 999;

        $this->actingAs($owner)
            ->from(route('project.edit', $project->id))
            ->post(route('project.store'), [
                'id' => $project->id,
                'name' => $project->name,
                'real_name' => $project->real_name,
                'status_id' => ProjectStatus::STATUS_FINISHED,
                'enterprise_id' => $project->enterprise_id,
                'responsible_id' => $project->responsible_id,
                'discount' => 25,
                'data' => [
                    'suggested_tasks' => json_encode($tasks),
                    'ai_usage_percent' => 80,
                ],
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $fresh = $project->fresh();
        $this->assertSame(ProjectStatus::STATUS_IN_PROGRESS, (int) $fresh->status_id);
        $this->assertSame(120.0, (float) $fresh->data['suggested_tasks'][0]['unit_price']);
        $this->assertNull($fresh->discount);
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
