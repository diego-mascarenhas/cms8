<?php

namespace Tests\Feature;

use App\Mail\ProjectBudgetQuoteMail;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectBudgetAuthorizeEmailTest extends TestCase
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
            ContactStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    #[Test]
    public function budgeted_project_can_be_authorized_and_emails_enterprise_contact(): void
    {
        Mail::fake();

        [$user, $project, $contact] = $this->createBudgetedProject();

        $response = $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.authorize-budget', $project->id));

        $response->assertRedirect(route('project.show', $project->id));
        $response->assertSessionHas('success');

        $project->refresh();
        $this->assertSame(ProjectStatus::STATUS_AUTHORIZED, (int) $project->status_id);
        $this->assertSame($contact->email, data_get($project->data, 'budget_email.to_email'));
        $this->assertSame($contact->id, data_get($project->data, 'budget_email.contact_id'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.tracking_token'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.sent_at'));

        Mail::assertSent(ProjectBudgetQuoteMail::class, function (ProjectBudgetQuoteMail $mail) use ($contact): bool
        {
            $html = $mail->render();

            return $mail->hasTo($contact->email)
                && str_contains($html, 'idoneo-logo.svg')
                && str_contains($html, 'IDONEO')
                && ! str_contains($html, 'humano');
        });
    }

    #[Test]
    public function admin_saving_status_as_authorized_sends_quote_email(): void
    {
        Mail::fake();

        [$user, $project, $contact] = $this->createBudgetedProject();

        $response = $this->actingAs($user)
            ->from(route('project.edit', $project->id))
            ->post(route('project.store'), [
                'id' => $project->id,
                'name' => $project->name,
                'real_name' => $project->real_name,
                'status_id' => ProjectStatus::STATUS_AUTHORIZED,
                'enterprise_id' => $project->enterprise_id,
                'responsible_id' => $project->responsible_id,
                'data' => $project->data,
            ]);

        $response->assertRedirect(route('project.show', $project->id));
        $response->assertSessionHas('success');

        $project->refresh();
        $this->assertSame(ProjectStatus::STATUS_AUTHORIZED, (int) $project->status_id);
        $this->assertSame($contact->email, data_get($project->data, 'budget_email.to_email'));
        Mail::assertSent(ProjectBudgetQuoteMail::class);
    }

    #[Test]
    public function authorize_requires_budgeted_status(): void
    {
        Mail::fake();

        [$user, $project] = $this->createBudgetedProject();
        $project->forceFill(['status_id' => ProjectStatus::STATUS_BUDGET])->save();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.authorize-budget', $project->id))
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertSame(ProjectStatus::STATUS_BUDGET, (int) $project->fresh()->status_id);
    }

    #[Test]
    public function open_and_click_tracking_update_budget_email_meta(): void
    {
        Mail::fake();

        [$user, $project] = $this->createBudgetedProject();
        $this->actingAs($user)->post(route('project.authorize-budget', $project->id));
        $project->refresh();
        $trackingToken = data_get($project->data, 'budget_email.tracking_token');
        $previewToken = data_get($project->data, 'budget_preview_token');

        $this->get(route('project.budget-email.track-open', $trackingToken))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $project->refresh();
        $this->assertNotNull(data_get($project->data, 'budget_email.opened_at'));

        $this->get(route('project.budget-email.track-click', $trackingToken))
            ->assertRedirect(route('project.budget-preview', $previewToken));

        $project->refresh();
        $this->assertNotNull(data_get($project->data, 'budget_email.clicked_at'));
    }

    /**
     * @return array{0: User, 1: Project, 2: Contact}
     */
    private function createBudgetedProject(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $enterprise = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Acme Client',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Client',
            'surname' => 'Contact',
            'email' => 'client.contact@example.com',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);
        $enterprise->contacts()->attach($contact->id);

        $token = 'budgetAuthToken'.bin2hex(random_bytes(12));
        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_BUDGETED,
            'name' => 'Store publish',
            'real_name' => 'Store publish project',
            'data' => [
                'budget_preview_token' => $token,
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

        return [$user, $project, $contact];
    }
}
