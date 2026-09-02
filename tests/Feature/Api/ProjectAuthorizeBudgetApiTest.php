<?php

namespace Tests\Feature\Api;

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
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectAuthorizeBudgetApiTest extends TestCase
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
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        config(['projects.budget_preview_base_url' => null]);
    }

    public function test_show_includes_public_budget_urls(): void
    {
        [$user, $project, , $token] = $this->createBudgetProject();
        $previewToken = data_get($project->data, 'budget_preview_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('preview_url', route('project.budget-preview', $previewToken, true))
            ->assertJsonPath('download_url', route('project.budget-preview', $previewToken, true).'?download=1')
            ->assertJsonPath('data.quote_contact.name', 'Client Contact')
            ->assertJsonPath('data.quote_contact.email', 'quote.contact@example.com')
            ->assertJsonMissingPath('data.client.contacts');

        $this->assertSame($user->id, $project->responsible_id);
    }

    public function test_show_uses_frontend_preview_url_when_configured(): void
    {
        config(['projects.budget_preview_base_url' => 'https://estimator.idoneo.dev']);

        [, $project, , $token] = $this->createBudgetProject();
        $previewToken = data_get($project->data, 'budget_preview_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('preview_url', 'https://estimator.idoneo.dev/p/budget/'.$previewToken);
    }

    public function test_show_uses_the_estimator_origin_for_preview_url(): void
    {
        config(['projects.budget_preview_base_url' => 'https://estimator.idoneo.dev']);

        [, $project, , $token] = $this->createBudgetProject();
        $previewToken = data_get($project->data, 'budget_preview_token');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'https://presu.humano.app',
        ])
            ->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('preview_url', 'https://presu.humano.app/p/budget/'.$previewToken);

        $project->refresh();
        $this->assertSame('https://presu.humano.app', data_get($project->data, 'budget_preview_base_url'));
    }

    public function test_budget_status_can_be_authorized_and_emailed(): void
    {
        Mail::fake();

        [, $project, $contact, $token] = $this->createBudgetProject();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/authorize-budget');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_id', ProjectStatus::STATUS_AUTHORIZED)
            ->assertJsonPath('preview_url', route('project.budget-preview', data_get($project->data, 'budget_preview_token'), true));

        $project->refresh();
        $this->assertSame(ProjectStatus::STATUS_AUTHORIZED, (int) $project->status_id);
        $this->assertSame($contact->email, data_get($project->data, 'budget_email.to_email'));
        $this->assertNotEmpty(data_get($project->data, 'budget_email.tracking_token'));

        Mail::assertSent(ProjectBudgetQuoteMail::class, function (ProjectBudgetQuoteMail $mail) use ($contact): bool
        {
            return $mail->hasTo($contact->email)
                && $mail->hasBcc('quotes@example.test');
        });
    }

    public function test_authorize_budget_requires_contact_email(): void
    {
        Mail::fake();

        [, $project, $contact, $token] = $this->createBudgetProject();
        $contact->forceFill(['email' => null])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/authorize-budget')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Mail::assertNothingSent();
    }

    public function test_collaborator_cannot_send_quote_email(): void
    {
        Mail::fake();

        [, $project] = $this->createBudgetProject();

        $collaborator = User::factory()->withPersonalTeam()->create();
        $team = $project->team;
        $collaborator->teams()->attach($team);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();
        $collaborator->assignRole('collaborator');
        $project->forceFill(['responsible_id' => $collaborator->id])->save();

        $token = $collaborator->createToken('idoneo-projects-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/projects/'.$project->id.'/authorize-budget')
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: Project, 2: Contact, 3: string}
     */
    private function createBudgetProject(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
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
            'name' => 'API Quote Client',
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
            'email' => 'quote.contact@example.com',
            'responsible_id' => $user->id,
            'creator_id' => $user->id,
            'status_id' => 1,
            'country' => 724,
            'language' => 'es',
        ]);
        $enterprise->contacts()->attach($contact->id);

        $previewToken = 'apiBudgetToken'.bin2hex(random_bytes(12));
        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $enterprise->id,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_BUDGET,
            'name' => 'Estimator quote',
            'real_name' => 'Estimator quote project',
            'data' => [
                'budget_preview_token' => $previewToken,
                'suggested_tasks' => [
                    [
                        'title' => 'Discovery',
                        'estimated_hours' => 4,
                        'resource_level' => 'Consultor',
                        'unit_price' => 260,
                        'included' => true,
                    ],
                ],
            ],
        ]));

        $token = $user->createToken('idoneo-projects-test')->plainTextToken;

        return [$user, $project, $contact, $token];
    }
}
