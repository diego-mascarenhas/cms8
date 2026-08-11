<?php

namespace Tests\Feature;

use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectBudgetPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
    }

    #[Test]
    public function public_budget_preview_renders_pdf_style_table(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Acme Client',
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
        $status = ProjectStatus::query()->firstOrFail();

        $token = 'testBudgetPreviewToken123456789012345678901234';
        Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => $status->id,
            'name' => 'Store publish',
            'real_name' => 'Store publish project',
            'discount' => 30,
            'data' => [
                'budget_preview_token' => $token,
                'ai_usage_percent' => 70,
                'dimension' => 'Medium scope mobile store release.',
                'estimated_times' => 'About 1 week.',
                'resources' => 'One Senior profile.',
                'token_consumption' => [
                    'savings_percent' => 57,
                ],
                'suggested_tasks' => [
                    [
                        'title' => 'iOS signing',
                        'estimated_hours' => 3,
                        'resource_level' => 'Senior',
                        'unit_price' => 225,
                        'estimated_tokens' => 60000,
                        'included' => true,
                    ],
                ],
            ],
        ]));

        $response = $this->get(route('project.budget-preview', $token));

        $response->assertOk();
        $response->assertSee('Store publish project', false);
        $response->assertSee('Acme Client', false);
        $response->assertSee('iOS signing', false);
        $response->assertSee(__('Details'), false);
        $response->assertSee('report-header', false);
        $response->assertSee('highlight', false);
        $response->assertSee('idoneo-logo.svg', false);
        $response->assertSee(__('Discount on labor'), false);
        $response->assertSee('30%', false);
        $response->assertDontSee('layout-wrapper', false);
        $response->assertSee(__('Important before accepting'), false);
        $response->assertSee(__('Accept quote'), false);
        $response->assertSee(__('Request reformulation'), false);
        $response->assertSee(
            explode(':remaining', __('The remaining amount (:remaining): if you authorize the debit, it will be charged when the project is completed; if you pay yourself, payment is due upon completion or within 30 days after the agreed completion date.'))[0],
            false,
        );
        $response->assertSee(
            explode(':amount', __('I authorize the debit of this amount (:amount).'))[0],
            false,
        );
        $response->assertDontSee(__('Amounts do not include VAT.'), false);
        $response->assertDontSee('already includes estimated', false);
        $response->assertDontSee('ahorro estimado', false);
        $response->assertDontSee('Stripe', false);
        $response->assertDontSee('MCP/TOON', false);
    }

    #[Test]
    public function public_budget_preview_accepts_quote_with_deposit_confirmation(): void
    {
        $token = $this->createBudgetPreviewProject()['token'];

        $response = $this->from(route('project.budget-preview', $token))
            ->post(route('project.budget-preview.accept', $token), [
                'accepted_by_name' => 'Jane Client',
                'accept_debit' => '1',
            ]);

        $response->assertRedirect(route('project.budget-preview', $token));
        $response->assertSessionHas('budget_response_success');

        $project = Project::withoutGlobalScopes()
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();

        $this->assertSame('accepted', data_get($project->data, 'budget_client_response.status'));
        $this->assertSame('Jane Client', data_get($project->data, 'budget_client_response.accepted_by_name'));
        $this->assertTrue((bool) data_get($project->data, 'budget_client_response.accept_debit'));
        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $project->status_id);

        $this->get(route('project.budget-preview', $token))
            ->assertOk()
            ->assertSee(__('Quote accepted'), false)
            ->assertDontSee(__('Accept quote'), false);
    }

    #[Test]
    public function accepted_quote_heals_stale_project_status_on_preview(): void
    {
        $created = $this->createBudgetPreviewProject([
            'budget_client_response' => [
                'status' => 'accepted',
                'accepted_by_name' => 'Jane Client',
                'message' => null,
                'responded_at' => now()->toIso8601String(),
                'ip' => '127.0.0.1',
            ],
        ]);
        $token = $created['token'];
        $created['project']->forceFill(['status_id' => ProjectStatus::STATUS_BUDGET])->save();

        $this->get(route('project.budget-preview', $token))->assertOk();

        $project = Project::withoutGlobalScopes()
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();

        $this->assertSame(ProjectStatus::STATUS_APPROVED, (int) $project->status_id);
    }

    #[Test]
    public function public_budget_preview_accepts_quote_without_debit_authorization(): void
    {
        $token = $this->createBudgetPreviewProject()['token'];

        $response = $this->from(route('project.budget-preview', $token))
            ->post(route('project.budget-preview.accept', $token), [
                'accepted_by_name' => 'Jane Client',
            ]);

        $response->assertRedirect(route('project.budget-preview', $token));
        $response->assertSessionHas('budget_response_success');
        $response->assertSessionDoesntHaveErrors('accept_debit');

        $project = Project::withoutGlobalScopes()
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();

        $this->assertSame('accepted', data_get($project->data, 'budget_client_response.status'));
        $this->assertFalse((bool) data_get($project->data, 'budget_client_response.accept_debit'));
    }

    #[Test]
    public function public_budget_preview_requests_reformulation_with_message(): void
    {
        $token = $this->createBudgetPreviewProject()['token'];

        $response = $this->from(route('project.budget-preview', $token))
            ->post(route('project.budget-preview.reformulate', $token), [
                'name' => 'Jane Client',
                'message' => 'Please reduce Senior hours and clarify token costs.',
            ]);

        $response->assertRedirect(route('project.budget-preview', $token));
        $response->assertSessionHas('budget_response_success');

        $project = Project::withoutGlobalScopes()
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();

        $this->assertSame('reformulation_requested', data_get($project->data, 'budget_client_response.status'));
        $this->assertSame(
            'Please reduce Senior hours and clarify token costs.',
            data_get($project->data, 'budget_client_response.message'),
        );
        $this->assertSame(ProjectStatus::STATUS_WAITING_FOR_RESPONSE, (int) $project->status_id);

        $this->get(route('project.budget-preview', $token))
            ->assertOk()
            ->assertSee(__('Reformulation requested'), false)
            ->assertSee('Please reduce Senior hours and clarify token costs.', false);
    }

    #[Test]
    public function public_budget_preview_rejects_second_response(): void
    {
        $token = $this->createBudgetPreviewProject([
            'budget_client_response' => [
                'status' => 'accepted',
                'accepted_by_name' => 'Already Done',
                'message' => null,
                'responded_at' => now()->toIso8601String(),
                'ip' => '127.0.0.1',
            ],
        ])['token'];

        $this->from(route('project.budget-preview', $token))
            ->post(route('project.budget-preview.reformulate', $token), [
                'name' => 'Someone Else',
                'message' => 'Trying to change an accepted quote.',
            ])
            ->assertRedirect(route('project.budget-preview', $token))
            ->assertSessionHas('budget_response_error');

        $project = Project::withoutGlobalScopes()
            ->where('data->budget_preview_token', $token)
            ->firstOrFail();

        $this->assertSame('accepted', data_get($project->data, 'budget_client_response.status'));
    }

    /**
     * @param  array<string, mixed>  $dataOverrides
     * @return array{token: string, project: Project}
     */
    private function createBudgetPreviewProject(array $dataOverrides = []): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Acme Client',
            'type_id' => 1,
            'status_id' => 1,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));
        $status = ProjectStatus::query()->firstOrFail();
        $token = 'testBudgetPreviewToken'.bin2hex(random_bytes(16));

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => $status->id,
            'name' => 'Store publish',
            'real_name' => 'Store publish project',
            'discount' => 0,
            'data' => array_merge([
                'budget_preview_token' => $token,
                'ai_usage_percent' => 70,
                'dimension' => 'Medium scope mobile store release.',
                'estimated_times' => 'About 1 week.',
                'resources' => 'One Senior profile.',
                'token_consumption' => [
                    'savings_percent' => 57,
                ],
                'suggested_tasks' => [
                    [
                        'title' => 'iOS signing',
                        'estimated_hours' => 3,
                        'resource_level' => 'Senior',
                        'unit_price' => 225,
                        'estimated_tokens' => 60000,
                        'included' => true,
                    ],
                ],
            ], $dataOverrides),
        ]));

        return ['token' => $token, 'project' => $project];
    }
}
