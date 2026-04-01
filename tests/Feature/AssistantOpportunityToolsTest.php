<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Module;
use App\Models\OpportunityStage;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\OpportunityStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantOpportunityToolsTest extends TestCase
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

    public function test_list_opportunity_stages_returns_slugs(): void
    {
        $user = $this->createAdminWithTeam();
        $this->enableOpportunitiesModule($user);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $user->currentTeam->id, null);

        $out = $service->execute('list_opportunity_stages', []);
        $this->assertStringContainsString('qualification', $out);
        $this->assertStringContainsString('Qualification', $out);
    }

    public function test_create_opportunity_persists_row(): void
    {
        $user = $this->createAdminWithTeam();
        $this->enableOpportunitiesModule($user);

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $user->currentTeam->id, null);

        $out = $service->execute('create_opportunity', [
            'contact_id' => $contact->id,
            'name' => 'Assistant deal',
            'stage_slug' => 'proposal',
            'description' => 'From assistant',
        ]);

        $this->assertStringContainsString('Opportunity created', $out);
        $this->assertStringContainsString('Assistant deal', $out);

        $proposal = OpportunityStage::query()->where('slug', 'proposal')->firstOrFail();

        $this->assertDatabaseHas('opportunities', [
            'contact_id' => $contact->id,
            'name' => 'Assistant deal',
            'team_id' => $user->currentTeam->id,
            'opportunity_stage_id' => $proposal->id,
        ]);
    }

    public function test_create_opportunity_fails_when_module_disabled(): void
    {
        $user = $this->createAdminWithTeam();

        $contact = Contact::factory()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $service = app(AssistantToolsService::class);
        $service->clearRequestContext();
        $service->setRequestContext($user->id, $user->currentTeam->id, null);

        $out = $service->execute('create_opportunity', [
            'contact_id' => $contact->id,
            'name' => 'Should fail',
        ]);

        $this->assertStringContainsString('opportunities module is not enabled', $out);
    }

    private function createAdminWithTeam(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->teams()->attach($team->id, ['role' => 'admin']);
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
