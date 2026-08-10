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
        $response->assertDontSee('layout-wrapper', false);
    }
}
