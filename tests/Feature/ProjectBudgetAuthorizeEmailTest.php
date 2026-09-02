<?php

namespace Tests\Feature;

use App\Mail\ProjectBudgetQuoteMail;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\ProjectBudgetQuoteMailService;
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
        config(['projects.budget_preview_base_url' => null]);
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
        $this->assertSame('quotes@example.test', data_get($project->data, 'budget_email.bcc_email'));
        $this->assertSame($contact->id, data_get($project->data, 'budget_email.contact_id'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.tracking_token'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.sent_at'));

        $this->assertSame(
            1,
            app(ProjectBudgetQuoteMailService::class)->countSentForTeam((int) $user->current_team_id),
        );

        Mail::assertSent(ProjectBudgetQuoteMail::class, function (ProjectBudgetQuoteMail $mail) use ($contact): bool
        {
            $html = $mail->render();

            return $mail->hasTo($contact->email)
                && $mail->hasFrom('quotes@example.test')
                && $mail->hasBcc('quotes@example.test')
                && str_contains($html, __('Hello :name,', ['name' => 'Client Contact']))
                && str_contains($html, __('This quote covers the following project:'))
                && str_contains($html, 'Un sitio web institucional desarrollado en WordPress.')
                && ! str_contains($html, 'El cliente solicita')
                && str_contains($html, __('Dimension'))
                && str_contains($html, 'Small institutional WordPress site.')
                && str_contains($html, 'It covers hosting and four core pages.')
                && str_contains($html, __('Times'))
                && str_contains($html, 'Two to three weeks.')
                && str_contains($html, 'Fase 1: setup.')
                && str_contains($html, __('Resources'))
                && str_contains($html, 'One WordPress developer.')
                && ! str_contains($html, 'iOS signing')
                && ! str_contains($html, 'Certificate setup')
                && ! str_contains($html, 'Estimado')
                && ! str_contains($html, 'Dear ')
                && str_contains($html, '/p/budget-mail/')
                && str_contains($html, '/click')
                && ! str_contains($html, 'localhost:3007')
                && str_contains($html, '#0d9488')
                && ! str_contains($html, '#4361f7')
                && ! str_contains($html, 'logo-light.svg')
                && ! str_contains($html, 'REVISION ALPHA')
                && ! str_contains($html, '30%')
                && ! str_contains($html, 'humano');
        });
    }

    #[Test]
    public function quote_email_does_not_bcc_when_recipient_is_the_sender(): void
    {
        Mail::fake();

        [$user, $project, $contact] = $this->createBudgetedProject();
        $contact->forceFill(['email' => 'quotes@example.test'])->save();

        $this->actingAs($user)->post(route('project.authorize-budget', $project->id));

        $project->refresh();
        $this->assertNull(data_get($project->data, 'budget_email.bcc_email'));

        Mail::assertSent(ProjectBudgetQuoteMail::class, function (ProjectBudgetQuoteMail $mail): bool
        {
            return $mail->hasTo('quotes@example.test') && ! $mail->hasBcc('quotes@example.test');
        });
    }

    #[Test]
    public function failed_send_does_not_mark_email_as_sent(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP rejected'));

        [$user, $project] = $this->createBudgetedProject();

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.authorize-budget', $project->id))
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');

        $project->refresh();
        $this->assertNull(data_get($project->data, 'budget_email.sent_at'));
        $this->assertSame(ProjectStatus::STATUS_BUDGETED, (int) $project->status_id);
    }

    #[Test]
    public function authorize_requires_configured_sender(): void
    {
        Mail::fake();

        [$user, $project] = $this->createBudgetedProject();
        $user->currentTeam?->settings()->whereIn('key', ['mail_from_name', 'mail_from_address'])->delete();
        $user->currentTeam?->unsetRelation('settings');

        $this->actingAs($user)
            ->from(route('project.show', $project->id))
            ->post(route('project.authorize-budget', $project->id))
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertSame(ProjectStatus::STATUS_BUDGETED, (int) $project->fresh()->status_id);
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
        $team->setSetting('mail_from_name', 'Estimator', [
            'group' => 'email',
            'type' => 'text',
            'is_encrypted' => false,
        ]);
        $team->setSetting('mail_from_address', 'quotes@example.test', [
            'group' => 'email',
            'type' => 'email',
            'is_encrypted' => false,
        ]);

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
                'ai_interpretation' => 'El cliente solicita un sitio web institucional desarrollado en WordPress.',
                'dimension' => 'Small institutional WordPress site. It covers hosting and four core pages.',
                'estimated_times' => 'Two to three weeks. Fase 1: setup. Fase 2: launch.',
                'resources' => 'One WordPress developer.',
                'suggested_tasks' => [
                    [
                        'title' => 'iOS signing',
                        'description' => 'Certificate setup and store publishing for the iOS build.',
                        'estimated_hours' => 3,
                        'resource_level' => 'Senior',
                        'unit_price' => 225,
                        'estimated_tokens' => 60000,
                        'included' => true,
                    ],
                    [
                        'title' => 'Internal QA only',
                        'description' => 'This optional task should not appear in the quote email.',
                        'included' => false,
                    ],
                ],
            ],
        ]));

        return [$user, $project, $contact];
    }
}
