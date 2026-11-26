<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceType;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\Finance\ProjectCollaboratorProformaService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectCollaboratorProformaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            ContactStatusSeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ProjectStatusSeeder::class,
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Currency::query()->firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'status' => true]);
        InvoiceType::query()->firstOrCreate(['name' => 'Standard']);
    }

    #[Test]
    public function resolve_amount_uses_percent_of_project_total_by_default(): void
    {
        $service = app(ProjectCollaboratorProformaService::class);

        $this->assertSame(300.0, $service->resolveAmount(1000, 'percent', 30));
        $this->assertSame(250.0, $service->resolveAmount(1000, 'amount', 250));
    }

    #[Test]
    public function approved_project_show_lists_participant_proforma_rows(): void
    {
        [$user, $project, $participant] = $this->createApprovedProjectWithParticipant();

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee(__('Proformas de compra por colaborador'), false)
            ->assertSee($participant->name, false)
            ->assertSee($user->name, false)
            ->assertSee(__('Crear proformas seleccionadas'), false);
    }

    #[Test]
    public function approved_project_without_team_collaborators_shows_empty_proforma_banner(): void
    {
        [$user, $project] = $this->createApprovedProjectWithParticipant();
        $project->participants()->detach();
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $project->forceFill(['responsible_id' => 999999])->save();

        $this->actingAs($user)
            ->get(route('project.show', $project->id))
            ->assertOk()
            ->assertSee(__('Proformas de compra por colaborador'), false)
            ->assertSee(__('Colaboradores'), false)
            ->assertDontSee(__('Crear proformas seleccionadas'), false);
    }

    #[Test]
    public function creating_proformas_stores_draft_purchase_invoices_at_thirty_percent(): void
    {
        [$user, $project, $participant, $supplier] = $this->createApprovedProjectWithParticipant();

        $this->actingAs($user)
            ->post(route('project.collaborator-proformas', $project->id), [
                'collaborators' => [
                    [
                        'selected' => '1',
                        'user_id' => $participant->id,
                        'mode' => 'percent',
                        'value' => 30,
                    ],
                ],
            ])
            ->assertRedirect(route('project.show', $project->id))
            ->assertSessionHas('success');

        $invoice = Invoice::withoutGlobalScopes()
            ->where('operation', 'buy')
            ->where('status', 9)
            ->where('enterprise_id', $supplier->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(300.0, (float) $invoice->total_amount, 0.01);
        $this->assertSame(
            ProjectCollaboratorProformaService::referenceFor((int) $project->id, (int) $participant->id),
            $invoice->source_reference_id,
        );
        $this->assertSame(
            (int) $invoice->id,
            (int) data_get($project->fresh()->data, 'collaborator_proformas.'.$participant->id.'.invoice_id'),
        );
    }

    #[Test]
    public function fixed_amount_mode_creates_proforma_with_that_price(): void
    {
        [$user, $project, $participant, $supplier] = $this->createApprovedProjectWithParticipant();

        $this->actingAs($user)
            ->post(route('project.collaborator-proformas', $project->id), [
                'collaborators' => [
                    [
                        'selected' => '1',
                        'user_id' => $participant->id,
                        'mode' => 'amount',
                        'value' => 175.5,
                    ],
                ],
            ])
            ->assertRedirect(route('project.show', $project->id));

        $invoice = Invoice::withoutGlobalScopes()
            ->where('enterprise_id', $supplier->id)
            ->where('status', 9)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(175.5, (float) $invoice->total_amount, 0.01);
    }

    #[Test]
    public function does_not_duplicate_an_existing_collaborator_proforma(): void
    {
        [$user, $project, $participant] = $this->createApprovedProjectWithParticipant();

        $this->actingAs($user)
            ->post(route('project.collaborator-proformas', $project->id), [
                'collaborators' => [
                    [
                        'selected' => '1',
                        'user_id' => $participant->id,
                        'mode' => 'percent',
                        'value' => 30,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('project.collaborator-proformas', $project->id), [
                'collaborators' => [
                    [
                        'selected' => '1',
                        'user_id' => $participant->id,
                        'mode' => 'percent',
                        'value' => 30,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('collaborators');

        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('operation', 'buy')->where('status', 9)->count());
    }

    /**
     * @return array{0: User, 1: Project, 2: User, 3: Enterprise}
     */
    private function createApprovedProjectWithParticipant(): array
    {
        $user = User::factory()->withPersonalTeam()->create([
            'name' => 'Leticia Admin',
        ]);
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $client = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Client Co',
            'type_id' => 1,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $supplier = Enterprise::withoutEvents(fn () => Enterprise::factory()->forTeam($team->id)->create([
            'name' => 'Germán SL',
            'type_id' => 2,
            'status_id' => 1,
            'responsible_id' => $user->id,
            'payment_type_id' => null,
            'invoice_type_id' => null,
        ]));

        $participant = User::factory()->create([
            'name' => 'Germán Rodriguez',
        ]);
        $team->users()->attach($participant, ['role' => 'editor']);

        Contact::withoutEvents(fn () => Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $participant->id,
            'name' => 'Germán Rodriguez',
            'current_enterprise_id' => $supplier->id,
        ]));

        Contact::withoutEvents(fn () => Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => 'Leticia Admin',
            'current_enterprise_id' => $supplier->id,
        ]));

        $project = Project::withoutEvents(fn () => Project::factory()->create([
            'team_id' => $team->id,
            'enterprise_id' => $client->id,
            'responsible_id' => $user->id,
            'status_id' => ProjectStatus::STATUS_APPROVED,
            'name' => 'Sitio web',
            'real_name' => 'Mantenimiento web',
            'price' => 1000,
            'discount' => 0,
            'data' => [
                'budget_client_response' => [
                    'status' => 'accepted',
                    'accepted_by_name' => 'Cliente',
                    'responded_at' => now()->toIso8601String(),
                ],
            ],
        ]));

        $project->participants()->attach($participant->id);

        return [$user, $project->fresh(), $participant, $supplier];
    }
}
