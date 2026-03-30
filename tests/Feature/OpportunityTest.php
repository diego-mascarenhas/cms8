<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\OpportunityStage;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\OpportunityStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        $this->seed(ContactStatusSeeder::class);
        $this->seed(OpportunityStageSeeder::class);
    }

    public function test_admin_can_view_opportunity_index(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableOpportunitiesModule($user);

        $response = $this->actingAs($user)->get(route('opportunity.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_opportunity(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableOpportunitiesModule($user);

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $stage = OpportunityStage::query()->orderBy('sort_order')->firstOrFail();

        $response = $this->actingAs($user)->post(route('opportunity.store'), [
            'contact_id' => $contact->id,
            'responsible_id' => $user->id,
            'opportunity_stage_id' => $stage->id,
            'name' => 'Test opportunity',
            'opened_at' => now()->toDateString(),
            'offering_kind' => 'none',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'contact_id' => $contact->id,
            'name' => 'Test opportunity',
            'team_id' => $user->currentTeam->id,
        ]);
    }

    public function test_contact_interaction_can_be_stored(): void
    {
        $user = $this->createUserWithRole('admin');
        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('contact.interactions.store', $contact->id), [
            'type' => 'note',
            'subject' => 'Follow-up',
            'body' => 'Called the contact.',
            'occurred_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('contact.show', $contact->id));

        $this->assertDatabaseHas('contact_interactions', [
            'contact_id' => $contact->id,
            'type' => 'note',
        ]);
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

    private function enableOpportunitiesModule(User $user): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'opportunities'],
            [
                'name' => 'Opportunities',
                'icon' => 'chart-line',
                'description' => 'CRM opportunities',
                'is_core' => true,
                'status' => 1,
            ],
        );
        $user->currentTeam->enableModule('opportunities');
    }
}
